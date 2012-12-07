#!/usr/bin/python

### Appendix H:  Sample Area-based GridServer Method ###

def _stats_area_months(self, request,
                       start=None,
                       end=None,
                       pstat=None,
                       param=None):

  """ 
  Statistic calculated across area over time (months).
  Returns a result grid (or grids) and data values as output.
  """

  log = request.logger
  start = start or request.start
  end = end or request.end
  param = param or request.param
  pstat = pstat or request.pstat

  ret = {param: {}}

  log.info("  _stats_area_months   start:%s end:%s" % ( start.strftime("%Y/%m"), end.strftime("%Y/%m")))

  # clip bounds
  self.init_bounds(request)

  # get keys for requested monthly grids
  monthly_keys = self.get_monthly_keys( start, end )
  log.debug("    monthly_key handles: %s" % ",".join( monthly_keys ))
  if not self.validate_keys(request, "monthlies", param, monthly_keys):
    log.error("Could not get monthly data")
  else:
    monthly_array = self.get_clipped_mdarray(request, "monthlies", param, monthly_keys)
    if monthly_array is None:
      log.error("Could not make clipped monthly array")
    else:
      if request.spatial_mask is not None:
        monthly_array[:,request.spatial_mask] = numpy.nan

        if pstat == 'pca':
          pca_grids, pca_eigval = self.get_pca_grids(monthly_array)
          pca_outfiles = []
          for i in range(len(pca_grids)):

            if request.spatial_mask is not None:
              pca_grids[i][request.spatial_mask] = numpy.nan

            name = request.gricket + "_pca" + str(i+1)
            new_filename = name + ".bil"
            new_path = os.path.join( request.output_dir, new_filename )
            new_ds = self.array_2_dataset( pca_grids[i], request.bounds, new_path )
            pca_outfiles.append( os.path.join(request.gricket, new_filename) )
          ret["pca_grids"] = pca_outfiles
          ret["pca_eigval"] = pca_eigval
        else:
          spatial_vals = self.get_spatial_values(param, pstat, monthly_array)
          ret = {param: self._stats_param_output(spatial_vals, param)}
          temporal_grid = self.get_temporal_grid(pstat, monthly_array)

          if request.spatial_mask is not None:
            temporal_grid[request.spatial_mask] = numpy.nan

          name = request.gricket + "_" + pstat
          new_filename = name + ".bil"
          new_path = os.path.join( request.output_dir, new_filename )
          new_ds = self.array_2_dataset(temporal_grid, request.bounds, new_path )
          ret["grid"] = os.path.join( request.gricket, new_filename )
        cells_used = numpy.count_nonzero(~numpy.isnan(monthly_array[0]))
        ret["extent"] = [request.bounds.leftlon, request.bounds.toplat,
                         request.bounds.rightlon, request.bounds.bottomlat]
        ret["size"] = [request.bounds.cols, request.bounds.rows]
        ret["cells_used"] = cells_used
        return ret
