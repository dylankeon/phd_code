<?

/*** Appendix G:  Web Code for the Simulation Results Page, Map-based Visualization, and Tabular Data ***/

// db.php:  Functions that connect to the spatial database to retrieve data
// rpc.php:  Code that handles Javascript AJAX calls to return data to the client
// table.php:  Data table popup web page


//Selected Code from db.php

  function getPersonInfo($dbh, $table, $lon, $lat, $tidx) {
    $query = "select pid, mid
              from $table
              where (tidx = $tidx OR alive = 'f')
              and ST_DWithin(ST_GeogFromText('SRID=4326;POINT($lon $lat)'), geography(geometry), 50)
              order by ST_Distance_Sphere( ST_GeometryFromText('POINT($lon $lat)',4326), geometry)
              limit 1";
    $result = pg_query($dbh, $query);
    if( pg_num_rows($result) == 0 ) {
      $out = "No person found within 100m of map click<br />\n";
    } else {
      $out = array();
      while( $row = pg_fetch_row($result) ) {
        $out[0] = $row[0];
        $row[1] == 1 ? $out[1] = 'Male' : $out[1] = 'Female';
      }
    }
    return $out;
  }

  // Query to populate google-viz-chart data table
  function getTimeSeries($dbh, $table, $pid) {
    $query = "SELECT id, tidx, alive, ST_X(geometry) AS lon, ST_Y(geometry)
              AS lat FROM $table WHERE pid = $pid ORDER BY tidx";
    $result = pg_query($dbh, $query);
    $rows = pg_num_rows($result);
    $col = 0;
    echo "data.addRows($rows);\n";
    while ($row = pg_fetch_array($result)) {
      $row['alive'] == 't' ? $alive = 'true' : $alive = 'false';
      echo "      data.setCell($col,0,".htmlspecialchars($row['id']).");\n";
      echo "      data.setCell($col,1,".htmlspecialchars($row['tidx']).");\n";
      echo "      data.setCell($col,2,".htmlspecialchars($alive).");\n";
      echo "      data.setCell($col,3,".htmlspecialchars(round($row['lon'],6)).");\n";
      echo "      data.setCell($col,4,".htmlspecialchars(round($row['lat'],6)).");\n";
      $col++;
    }
  }

  /* This version uses ogr2ogr, which supports retrieval of attributes into
     the GeoJSON format, and not just the geometry */
  function getPathPoints($table, $pid) {
    $filename = randString(12);
    $file = '/tmp/' . $filename . '.json';
    $util = "$conf['ogr2ogrPath']";
    $cmd = $util . ' -f "GeoJSON" ' . $file . ' PG:"dbname=<<redacted>> user=<<redacted>> password=<<redacted>>" -sql "select ST_Transform(geometry, 900913), tidx from ' . $table . ' where pid=' . $pid . '"';
    exec($cmd, $output, $ret);
    if ($ret == 0) {
      return $filename;
    } else {
      return 'There was an error.';
    }
  }


// Selected Code from rpc.php

  $proc = $_GET['proc'];

  if($proc == 'get_wave_heights') {
    $x = $_GET['x'];
    $y = $_GET['y'];
    $minx = $_GET['minx'];
    $miny = $_GET['miny'];
    $maxx = $_GET['maxx'];
    $maxy = $_GET['maxy'];
    $htfile = $_GET['htfile'];
    $extent = array($minx,$miny,$maxx,$maxy);
    $numsteps = $_GET['numsteps'];
    $rows = $_GET['rows'];
    $cols = $_GET['cols'];
    $geo_xy = projectPoint($x, $y, $current_proj, true);
    $img_xy = geoToPix($geo_xy[0], $geo_xy[1], $rows, $cols, $extent);
    $cmd = 'inc/read_sample ' . $cols . ' ' . $rows . ' ' . $numsteps . ' ' . $htfile . ' ' . $img_xy[0] . ' ' . $img_xy[1];
    $data = exec($cmd);
    $data = str_replace('nan', '0', $data);
    echo json_encode($data, JSON_NUMERIC_CHECK);
  } elseif($proc == 'get_path_data') {
    $x = $_GET['x'];
    $y = $_GET['y'];
    $table = $_GET['table'];
    $tidx = $_GET['tidx'];
    $person_info = getPersonInfo($dbh, $table, $x, $y, $tidx); // returns pid, gender
    if( is_array($person_info) ) {
      $data = getPathPoints($table, $person_info[0]);
      echo json_encode($data);
    } else {
      $d = array(0);
      echo json_encode($d);
    }
  } elseif($proc == 'get_count') {
    $table = $_GET['table'];
    $tidx = $_GET['tidx'];
    $data = getCount($dbh, $table, $tidx); // returns int count
    echo json_encode($data);
  } else {
    error("Called non-existent procedure: ", $proc);
  }

?>


<!--  Selected Code from table.php -->
<script type='text/javascript' src='https://www.google.com/jsapi'></script>
<script type='text/javascript'>
  var data, table;
  var tidx = <?echo $tidx;?>;

  google.load('visualization', '1', {packages:['table']});
  google.setOnLoadCallback(drawTable);

  function drawTable() {
    data = new google.visualization.DataTable();
    data.addColumn('number', 'Event ID');
    data.addColumn('number', 'Time Step');
    data.addColumn('boolean', 'Alive');
    data.addColumn('number', 'Longitude');
    data.addColumn('number', 'Latitude');
    <? if( is_array($person_info) ) {
         getTimeSeries($dbh, $table, $person_info[0]);
       } else {
         echo "No person exists close enough to your map click.  Please try again.";
       }
    ?>
    table = new google.visualization.Table(document.getElementById('popup_table'));
    table.draw(data, {width:500, showRowNumber:true});

    google.visualization.events.addListener(table, 'select', selectHandler);

    function selectHandler() {
      var selection = table.getSelection();
      var item = selection[0];
      var ts = data.getFormattedValue(item.row, 1);
      var tsint = parseInt(ts);
      window.opener.jumpToTimeStep(tsint);
      window.opener.hiliteSegment(tsint);
    }

    // set initial state of table and space-time path on map
    var max = data.getNumberOfRows();
    if( tidx < max ) {
      table.setSelection([{row:tidx, column:null}]);
      //window.opener.hiliteSegment(tidx);
    }
  }
</script>

