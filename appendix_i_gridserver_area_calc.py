#!/usr/bin/python

### Appendix I:  GridServer Area-based Statistical Calculations ###

def get_spatial_values(self, param, pstat, data):
  ### Computes mean across each grid area and returns the resulting set
  ### of values.
  ### AKA "spatial first"

  if param == 'ppt':
    rnda = 3
    rndb = 2
    rndc = 1
  else:
    rnda = 2
    rndb = 1
    rndc = 1

  spatial_vals = []

  # convert nan's if they exist (ie, bbox is partly over ocean)
  # this approach uses a numpy masked array to mask the nan's

  mdata = numpy.ma.masked_array(data, numpy.isnan(data))

  for i in mdata:
    if( pstat == 'mean' ):
      spatial_vals.append(round(numpy.ma.mean(i), rnda))
    elif( pstat == 'min' ):
      spatial_vals.append(round(numpy.ma.min(i), rndb))
    elif( pstat == 'max' ):
      spatial_vals.append(round(numpy.ma.max(i), rndb))
    elif( pstat == 'median' ):
      spatial_vals.append(round(numpy.ma.median(i), rndb))
    elif( pstat == 'stddev' ):
      spatial_vals.append(round(numpy.ma.std(i, ddof=1), rndc))

  return spatial_vals


def get_temporal_grid(self, pstat, data):
  ### Computes mean thru all columns and returns a new grid (ie, map
  ### algebra).
  ### AKA "temporal first"
  ### axis=0 computes thru columns

  # convert nan's if they exist (ie, bbox is partly over ocean)
  # this approach uses a numpy masked array to mask the nan's

  fill_val = -9999.
  mdata = numpy.ma.masked_array(data, numpy.isnan(data))

  if( pstat == 'mean' ):
    grid = numpy.ma.mean(mdata, axis=0)
  elif( pstat == 'min' ):
    grid = numpy.ma.min(mdata, axis=0)
  elif( pstat == 'max' ):
    grid = numpy.ma.max(mdata, axis=0)
  elif( pstat == 'median' ):
    grid = numpy.ma.median(mdata, axis=0)
  elif( pstat == 'stddev' ):
    grid = numpy.ma.std(mdata, axis=0, ddof=1)

  outgrid = grid.filled(fill_val)
  return outgrid
