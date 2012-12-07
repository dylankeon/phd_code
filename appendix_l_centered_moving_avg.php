<?

/*** Appendix L:  Calculating Centered Moving Averages ***/


/* Creates an array of centered moving average data values */
/* for a given data array and window size.                 */
/* Correctly handles both even- and odd-sized windows.     */

function centeredMovingAvg($data, $win) {

  // determine whether win is odd or even
  $odd = $win % 2 == 0 ? false : true;

  $param = $_POST['param'];
  $dec = $param != 'ppt' ? '%01.1f' : '%01.2f';
  $ct = count($data);

  // set proper halfwin for odd vs. even
  if( $odd ) {
    $halfwin = floor($win/2); //ie, win=5 gives halfwin=2
  } else {
    $halfwin = $win/2; //ie, win=10 gives halfwin=5
  }

  $end = $ct - $halfwin;

  // assign output nulls for unused data points at beginning of range
  for( $i=0; $i<$halfwin; $i++ ) {
    $out[$i] = 'null';
  }

  if( $odd ) {
    // calculate centered moving average across odd window
    for( $i=$halfwin; $i<$end; $i++ ) {
      $sum = 0;
      for( $j=($i-$halfwin); $j<=($i+$halfwin); $j++ ) {
        $sum += $data[$j];
      }
      $out[$i] = sprintf($dec, $sum/$win);
    }
  } else {
    // calculate centered moving average across even window
    // this is a 2-by-X centering method, where X is the even window value
    $cf1 = 1/$win; // coeff for calculating non-endpoint vals
    $cf2 = $cf1/2; // coeff for calculating endpoint vals
    for( $i=$halfwin; $i<$end; $i++ ) {
      $sum = 0;
      for( $j=($i-$halfwin); $j<=($i+$halfwin); $j++ ) {
        // window of 10 will have val index range of 0-10 (11 vals)
        $coeff = $j == ($i-$halfwin) || $j == ($i+$halfwin) ? $cf2 : $cf1;
        $sum += $coeff * $data[$j];
      }
      $out[$i] = sprintf($dec, $sum);
    }
  }

  // assign output nulls for unused data points at end of range
  for( $i=$end; $i<$ct; $i++ ) {
    $out[$i] = 'null';
  }

  // can end up with an array full of nulls with short time spans
  // this messes up chart ... so return false and don't chart it at all
  if( count(array_unique($out)) == 1 && $out[0] == 'null' ) {
    return false;
  } else {
    return $out;
  }
}

?>
