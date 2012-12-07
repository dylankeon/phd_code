/*** Appendix C:  Selected PHP Functions and JavaScript Code Related to Spatial Data Handling in the Portal Interface ***/

/*
Dynamically Calculating Estimated Simulation Time

As the portal user parameterizes a model run, they define the number of seconds simulated by each time step as well as the total number of time steps to calculate.  With this information, the portal uses a mix of PHP and JavaScript to calculate the estimated amount of time that will be simulated by the model run.  This number changes dynamically as the user adjusts their desired parameters.
*/

function displayTotalSize()
{
  var total_points = <?php echo $m_grid->getTotalDataPoints(); ?>;
  var len_time_step = (document.getElementById('Len_Time_Step')).value;
  var num_time_steps = (document.getElementById('Num_Time_Steps')).value;
  var output_freq = (document.getElementById('Output_Frequency')).value;

  var hours_elapsed = (((len_time_step * num_time_steps) / 60) / 60).toFixed(3);

  if( parseFloat(len_time_step) > 0.0 && parseInt(num_time_steps) > 0 && parseInt(output_freq) > 0 )
  {
    var size = ( num_time_steps / output_freq ) * total_points * 4;
    var out = '<b class="size_estimator">Estimated total output &asymp; ' + getNiceSize(size) + '</b><br/>' +
        '<span class="size_estimator" style="font-size : 80%;">Equivalent of simulating ' + hours_elapsed +
        ' hrs<br/></span>';
    if( out != last_out )
    {
      (document.getElementById('size_estimator')).innerHTML = out;
      last_out = out;
    }
  }
  else
  {
    if( last_out != '' )
    {
      (document.getElementById('size_estimator')).innerHTML = '<br/><span style="font-size : 80%;"><br/></span>';
      last_out = '';
    }
  }
}
