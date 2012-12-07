<!-- Appendix G:  Web Code for the Simulation Results Page, Map-based Visualization, and Tabular Data -->

<!-- result.js:  OpenLayers mapping code and all of the related custom Javascript that drives the animation, timekeeping, charting, and other client-side functionality. -->


<!-- Selected Code from result.js -->

<script type="text/javascript">

/** Build water depth chart **/

  $(document).ready(function() {
    chart = new Highcharts.Chart({
      chart: {
        renderTo: 'chartdiv',
        type: 'area',
        borderColor: '#bbb',
        borderWidth: 2,
        borderRadius: 5,
        zoomType: 'x',
        resetZoomButton: {
          position: {
            x: -10,
            y: 10
          },
          relativeTo: 'chart'
        },
        events: {
          click: function(event) {
            jumpToTimeStep(Math.round(parseFloat(event.xAxis[0].value)));
          }
        }
      },
      title: {
        text: 'Water Depth at Selected Point Across All Time Steps'
      },
      xAxis: {
        title: {
          text: 'Time Step'
        }
      },
      yAxis: {
        title: {
          text: 'Water Depth (m)'
        }
      },
      loading: {
        labelStyle: {
          top: '45%'
        }
      },
      tooltip: {
        shared: true,
        crosshairs: true
      },
      plotOptions: {
        series: {
          lineWidth: 1

