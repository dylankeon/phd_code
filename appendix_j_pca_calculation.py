#!/usr/bin/python

### Appendix J:  PCA Calculation in Python ###

def get_pca_grids(self, data):
  ### Computes principal components for the requested set of input grids.
  ### Returns the set of principal component arrays to be turned into
  ### grids, and an array of the matching eigenvectors expressed as
  ### percentage of variance explained.

  fill_val = -9999.
  mdata = numpy.ma.masked_array(data, numpy.isnan(data))

  # Get shape of 3D grids array and create a zero'd 2D array the same size,
  # then fill new 2D array with flattened 3D subarrays.
  # This is all to get a flattened array (one entire grid per subarray),
  # so the covariance matrix can be properly calculated across grids.
  
  d1,d2,d3 = mdata.shape
  mdata2 = numpy.ma.zeros([d1,d2*d3])
  for i in range(len(mdata)):
    mdata2[i] = mdata[i].flatten()

  # Mean-center the flattened array...axis=0 calculates the column mean,
  # which is subtracted from each array element in that column.
  
  mdata2 -= numpy.ma.mean(mdata2, axis=0)

  # Scale the mean-centered array...axis=0 calculates the column SD,
  # which is then used to divide each array element in that column.
  
  mdata2 /= numpy.ma.std(mdata2, axis=0, ddof=1)

  # Calculate the covariance matrix of the mean-centered, scaled array.
  
  covar = numpy.ma.cov(mdata2)

  # Use numpy.linalg.eig to calculate the eigenvalues and eigenvectors
  # of the covariance matrix. By its nature the covariance matrix is
  # always a square array, so we can properly use linalg.eig to do this.
  
  eval, evec = la.eig(covar)

  # Get array index for the eigenvalues, such that the largest eigenvalues
  # are first in the sort order. The index is used to sort both the
  # eigenvalues and the eigenvectors.
  # That is, the eigenvectors are properly sorted to maintain their
  # association with the matching eigenvalues.
  
  eval_abs = numpy.absolute(eval)
  idx = eval_abs.argsort()[::-1]
  sorted_eval = eval_abs[idx]
  sorted_evec = evec[idx]

  # Use the properly sorted (high to low) eigenvalues and associated
  # eigenvectors to calculate new arrays representing the principal
  # components. The new arrays are stored in the pc_grids numpy array
  # and returned. Matching eigenvalues are converted to percentage
  # variance explained, and placed in eigval[].
  #
  # First  PC is calculated as:
  #   PC1=(evec_col0_val0*mdata0)+(evec_col0_val1*mdata1)+...
  # Second PC is calculated as:
  #   PC2=(evec_col1_val0*mdata0)+(evec_col1_val1*mdata1)+...
  # and so on...
  
  eval_sum = numpy.sum(sorted_eval)
  pc_grids = numpy.zeros([d1,d2,d3])
  eigval = []
  val = 0

  for i in pc_grids:
    i = numpy.ma.zeros([d2,d3])
    for j in range(len(mdata)):
      i += sorted_evec[j][val] * mdata[j]
    pc_grids[val] = i.filled(fill_val)
    eigval.append(round((sorted_eval[val]/eval_sum)*100, 2))
    val += 1

  return pc_grids, eigval
