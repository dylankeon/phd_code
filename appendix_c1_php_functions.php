<?

/*** Appendix C:  Selected PHP Functions and JavaScript Code Related to Spatial Data Handling in the Portal Interface ***/

/*
Subgrid Selection

When a relatively coarse-resolution grid is selected in the portal interface, any grids of finer resolution are made available for selection as subgrids; however, they are only made available if they meet the 5:1 or 3:1 grid spacing ratio requirement.  The two functions below inspect metadata stored for each grid in the database, determine which grids of finer resolution can be made available for selection as aligned subgrids, and present the subgrid options to the user.
*/

function getPossibleChildGrids( $parent_grid, $level, $indent = 1 )
{
  global $GRID_NAME_LIST;
  $grids = '';

  $query = 'SELECT * FROM input_grids ' .
     'WHERE enabled = true AND (region_id = ' . $parent_grid['region_id'] . " OR region_id = 0) " .
     'AND ( spacing = ' . round(($parent_grid['spacing'] / 3),10) . ' OR spacing = ' . round(($parent_grid['spacing'] / 5),10) . ' ) ' .
     'AND ( max_x > ' . $parent_grid['min_x'] . ' AND min_x < ' . $parent_grid['max_x'] . ' AND ' .
           'max_y > ' . $parent_grid['min_y'] . ' AND min_y < ' . $parent_grid['max_y'] . ' ) ' .
     'ORDER BY db_table';

  $child_results = queryDB( $query );

  while( $grid = pg_fetch_array( $child_results, null, PGSQL_ASSOC ) )
  {
    $grids .= '<tr>';

    for( $i = 0; $i < $indent * 2; $i++ )
    {
      $grids .= '<td width="3%" rowspan="2"> </td>';
    }

    $num = $grid['input_grids_id'];
    $grids .= "<td width=\"5%\" align=\"right\"><input type=\"radio\" name=\"master_grid_id\" id=\"master_grid_id_$num\" value=\"$num\"/></td>\n" .
        "<td colspan=\"" . (9 - $indent) . "\">" .
                          "<label for=\"master_grid_id_$num\"><b>" . $grid['db_table'] . '</b></label>&nbsp;&nbsp;&nbsp;&nbsp;<a href="http://tsunamiportal.nacse.org/metadata/' . $grid['metadata_file'] .
        '" target="_blank" style="font-size : 80%">Metadata</a><br/>';
    $grids .= "</td></tr><tr><td></td><td colspan=\"" . (9 - $indent) . "\"><font style=\"font-size : 80%\">X-Range: " . $grid['min_x'] . ' to ' . $grid['max_x'] . "<br/>\n" .
        "Y-Range: " . $grid['min_y'] . ' to ' . $grid['max_y'] . "<br/>\n" .
        "Spacing: " . $grid['spacing'] . ' ' . $grid['units'] . "</br></font>\n";
    $grids .= "</td></tr>\n";

    $GRID_NAME_LIST[] = $grid['db_table'];

    if( --$level > 0 )
      $grids .= getPossibleChildGrids( $grid, $level, $indent + 1 );
  }
  return $grids;
}


//  getAddSubgrid( $curr_grid )
//
//  Returns a string containing an 'Add Sub Grid' button and a list
//  of possible sub grid spacings to choose from.
//
//  $curr_grid - The Grid Object to display possible sub grid spacings for

function getAddSubgrid( $curr_grid )
{
  $output = '';

  // Obtain a reference to the array of possible spacings for the current grid
  $spacings = $curr_grid->possible_spacings;
  // Get the tree depth of this grid node
  $node_depth = $curr_grid->getNodeDepth();

  // If there are any possible spacings
  if( count($spacings) > 0 )
  {
    // If this grid meets the sub grid nesting constraints:
    //  - The master grid can contain up to 4 sub grids
    //  - Sub grids of the master grid can contain only one sub grid
    //  - Sub grids of sub grids of the master grid cannot contain any further sub grids
    if( ( $node_depth == 1 && count($curr_grid->child_grids) < 4) ||
        ( $node_depth == 2 && count($curr_grid->child_grids) < 1 ) )
    {
      // Setup a table to contain the button and spacing options
      $output = "<table width=\"100%\"><tr><td align=\"left\" width=\"30%\">\n";
      // Setup the 'Add Sub Grid' button
      $output .= "<input type=\"submit\" name=\"add_new_grid[" . $curr_grid->grid_id . "]\" value=\"Add Sub Grid\" class=\"submit_small\"/>\n";
      $output .= "</td><td align=\"left\">\n";

      $spacing_checked = false;

      // Loop over each possible sub grid spacing
      foreach( $spacings as $spacing )
      {
        // Create a spacing id to be decoded by the GridController
        $spacing_id = $spacing['spacing'] . '=' . $spacing['units'] . '=' . $spacing['db_table'];
        if( !$spacing_checked )
        {
          $checked = 'checked="checked"';
          $spacing_checked = true;
        }
        else
        {
          $checked = '';
        }

        // Create a radio button and label for this spacing
        $output .= "<input type=\"radio\" name=\"grid_spacing_" . $curr_grid->grid_id . "\" id=\"grid_spacing_" . $curr_grid->grid_id . $spacing['db_table'] . "\" value=\"$spacing_id\" $checked/>\n";
        $output .= "<label for=\"grid_spacing_" . $curr_grid->grid_id . $spacing['db_table'] . "\">" . $spacing['db_table'] . "</label>\n";
      }
      $output .= "</td></tr></table>\n";
    }
    else
    {
      // This grid cannot contain any more sub grids.  Disallowed by the nesting constraints.
      $output .= "No more sub grids allowed for this grid<br>\n";
    }
  }



/*
Calculating and Drawing Grid Extents on Selection Map

Each user-defined grid extent is displayed on the selection map after the user clicks .Update Display. or proceeds to the next step in the interface.  Latitude and longitude values are mapped to pixel coordinates, and the functions below draw the necessary lines to form the extent of each grid and apply a transparent shaded overlay to the extent box.
*/

function drawGrid( $img )
{
  global $SEL_GRID;
  global $bg_color, $text_color, $range_color;
  $grid = $this->grid_info;

  $color = $text_color;
  $bg_color = $bg_color;

  if( isset($grid['overlapping']) && $grid['overlapping'] == true )
  {
    $color = colorallocate( $img, 204, 0, 0 );
  }

  if( !is_null($this->grid_bound) && !isset($grid['sibling_grid']) ) {
    $this->grid_bound->drawGrid($img);
  }

  if( !is_null($grid['min_x']) ) {

    if((isset($grid['overlapping']) and $grid['overlapping'] != true ) or (!isset($grid['overlapping']))){
      imagefilledrectangle( $img, $grid['left'], $grid['top'], $grid['left'] + $grid['x_length_scaled'], $grid['top'] + $grid['y_length_scaled'], $bg_color );
    }
    imagerectangle( $img, $grid['left'], $grid['top'], $grid['left'] +
      $grid['x_length_scaled'],
      $grid['top'] + $grid['y_length_scaled'],
      $color );
    $grid['caption'] = $grid['grid_id'] . ': ' . $grid['name'];
    $caption_width = $this->font_width * strlen( $grid['caption'] );
    if( $caption_width > $grid['x_length_scaled'] )
    {
      $grid['caption'] = substr( $grid['caption'], 0,
      intval( $grid['x_length_scaled'] / $this->font_width ) - 2 ) . '..';
    }

    imagestring($img, $this->font_size, $grid['left'] + 3, $grid['top'] + 2, $grid['caption'], $color);

    $table_name_parts = explode( '_', $grid['db_table'] );
    $caption2 = '(' . $table_name_parts[count($table_name_parts) - 1] . ')';
    $caption_width = $this->font_width * strlen( $caption2 );
    if( $caption_width > $grid['x_length_scaled'] )
    {
      $caption2 = substr( $caption2, 0, intval( $grid['x_length_scaled'] / $this->font_width ) - 3 ) . '..)';
    }

    if( $grid['y_length_scaled'] > (($this->font_height * 2) + 3) )
    {

      imagestring($img, $this->font_size, $grid['left'] + 3, ($grid['top'] + 2) + $this->font_height + 1 ,
      $caption2, $color);
    }

  }
  $this->drawChildGrids( $img );
}

function drawChildGrids( $img )
{
  $child_grids = $this->child_grids;
  foreach( $child_grids as $idx => $child_grid )
  {
    $child_grid = $child_grids[$idx];
    $child_grid->drawGrid( $img );
  }
}

?>
