/*** Appendix K:  GridStats Client-side Code Samples ***/


/* Sample Change Function */

// for when point scale changes to area scale and vice-versa
$("input[name='spatial']").change(function(e){
  removePolyLayers();
  removeBoxLayer();
  disableBoxDraw();
  disablePoint();
  setStat();
  $('#click_map_text').removeClass('red_text');
  if( $(this).val() == 'point' ) {
    disableArea();
    uncheckSubAreas();
    on_select_map_click();
    $('#click_map_text').addClass('red_text');
  } else if( $(this).val() == 'area' ) {
    removePointLayer();
    enableBoxDraw();
    $('#pca').removeAttr('disabled');
    $('#pca_label').removeClass('disabled');
    $('#draw_box_text').addClass('red_text');
    if( !$("input[name='areas']:checked").val() ) {
      $('#box').prop('checked', true);
    }
  } else if( $(this).val() == 'full' ) {
  $('#draw_box_text').removeClass('red_text');
    removePointLayer();
    uncheckSubAreas();
    if( $('#pca').prop('checked', true) ) {
      $('#pca').attr('disabled', 'disabled');
      $('#pca_label').addClass('disabled');
      $('#mean').prop('checked', true);
    }
  }
});



/* Sample Menu Updater */

var mo_days = Array(31,28,31,30,31,30,31,31,30,31,30,31);

// disables/enables days of month in <select>s based on new selection
// called when changes are made to month dropdowns
// also called from updateMonths()
function updateDays(pre, se) {

  var sy, sm, sd;
  var sy = parseInt($('#'+pre+'_'+se+'yr').val(), 10);
  var sm = parseInt($('#'+pre+'_'+se+'mo').val(), 10);
  var sd = parseInt($('#'+pre+'_'+se+'dy').val(), 10);
  var ld = mo_days[sm-1];

  // assign 29 as last day of Feb in a leap year
  if( sm == 2 ) {
    if( sy % 400 == 0 ) { // leap year
      ld = 29;
    } else if( sy % 100 == 0 ) { // not leap year
      ld = 28;
    } else if( sy % 4 == 0 ) { // leap year
      ld = 29;
    } else { // not leap year
      ld = 28;
    }
  }

  // re-enable any options that were disabled earlier
  $('#'+pre+'_'+se+'dy *').attr('disabled', false);

  // disable any days (i.e., 31) that don't exist in current month
  if( ld < 31 ) {
    for( var j=ld+1; j<=31; j++ ) {
      $('#'+pre+'_'+se+'dy option[value="'+j+'"]').attr('disabled', true);
    }
    // handle leap year - make sure 29 Feb is not disabled
    if( ld == 29 ) {
       $('#'+pre+'_'+se+'dy option[value="29"]').attr('disabled', false);
    }
  }



/* Sample Validator */

var elm = 'border';
var sty = '1px solid red';

function validateNumGrids(pstat) {
  var resp = 'You must select at least 3 grids when calculating '+pstat+'.';
  if( $('#'+pstat).prop('checked') == true ) {
    if( $('#mo_in_yr').prop('checked') == true ) {
      if( $('#miy_endyr').val() - $('#miy_startyr').val() < 3 ) {
        $('#miy_startyr').css(elm,sty);
        $('#miy_endyr').css(elm,sty);
        alert(resp);
        return false;
      }
    }
    if( $('#mo_in_rng').prop('checked') == true ) {
      start = $('#mir_startyr').val() + '/' + $('#mir_startmo').val() + '/' + 01;
      end = $('#mir_endyr').val() + '/' + $('#mir_endmo').val() + '/' + 01;
      chk = calcDays(start, end);
      if( chk < 58 ) { // under 3 months, accounting for inclusion of Feb
        $('#mir_startyr').css(elm,sty);
        $('#mir_startmo').css(elm,sty);
        $('#mir_endyr').css(elm,sty);
        $('#mir_endmo').css(elm,sty);
        alert(resp);
        return false;
      }
    }
    if( $('#days_in_rng').prop('checked') == true ) {
      start = $('#dir_startyr').val() + '/' + $('#dir_startmo').val() + '/' + $('#dir_startdy').val();
      end = $('#dir_endyr').val() + '/' + $('#dir_endmo').val() + '/' + $('#dir_enddy').val();
      chk = calcDays(start, end);
      if( chk < 3 ) {
        $('#dir_startyr').css(elm,sty);
        $('#dir_startmo').css(elm,sty);
        $('#dir_startdy').css(elm,sty);
        $('#dir_endyr').css(elm,sty);
        $('#dir_endmo').css(elm,sty);
        $('#dir_enddy').css(elm,sty);
        alert(resp);
        return false;
      }
    }
  }
  return true;
}



/* Sample AJAX Call */

function populateHucs(state_name, cookie_load_menu, cookie_zoom_poly){
  $.getJSON("rpc.php?proc=hucs&state2=" + state_name,
    function(data) {
      var ops1 = ['<option value="null">- HUC Name -</option>'];
      var ops2 = ['<option value="null">- HUC Code -</option>'];
      $.each(data, function(i,row) {
        // IDs are used as values so the menus can be synced
        ops1.push('<option value="' + row[0] + '">' + row[1] + '</option>')
        ops2.push('<option value="' + row[0] + '">' + row[2] + '</option>')
      });
      $("#huc_name").html( ops1.join('') );
      $("#huc_code").html( ops2.join('') );
      $('#hucs_loader').html('&nbsp;');
      // need to do from_cookie here rather than in cookie function area,
      // so that these can fire after AJAX call
      if( cookie_load_menu ) {
        $('#huc_name option[value="' + $.cookie('huc_name') + '"]').attr('selected', 'selected');
        $('#huc_code option[value="' + $.cookie('huc_name') + '"]').attr('selected', 'selected');
        if( cookie_zoom_poly ) {
          zoomToPoly('huc');
        }
      }
    }
  );
}
