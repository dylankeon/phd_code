/*** Appendix D:  Exporting and Packaging Selected Grid Extent ***/

// This code generates the output_bin_dfile() database
// function, which is used to export gridded TCP data
// in the binary format required by ARSC.

#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <sys/stat.h>
#include <unistd.h>
#include "executor/spi.h"

typedef unsigned char byte;

extern int output_bin_dfile( text *tbl_name, text *out_file, int x_min, int y_min, int x_max, int y_max, int flip_signs )
{
  FILE *fpout;
  char *tablename;
  char *outfilename;
  char outfilepath[256];
  char tmpPath[256] = "";
  bool flipSigns = ( flip_signs == 0 ? false : true );
  char query[512];

  unsigned int i, rows;
  int result;

  bool nullDepth = false;
  Datum datumDepth;
  float4 floatDepth;
  float bigendDepth;
  byte *lEnd = ((byte *) &floatDepth);
  byte *bEnd = ((byte *) &bigendDepth);
  byte cnt = 0;

  // Prepare tablename
  tablename = DatumGetCString(DirectFunctionCall1( textout, PointerGetDatum( tbl_name ) ));

  // Prepare outfilename
  outfilename = DatumGetCString(DirectFunctionCall1( textout, PointerGetDatum( out_file ) ));

  // Build the query statement
  sprintf( query, "SELECT depth::float4 FROM %s WHERE x >= %i AND x <= %i AND y >= %i AND y <= %i ORDER BY y ASC, x ASC;", tablename, x_min, x_max, y_min, y_max );

  // Open the output file for binary write access
  fpout = fopen(outfilename,"wb");

  if (fpout==NULL)
  {
    elog( ERROR, "Unable to open output file: '%s'", outfilename );
  }

  // Output file is open and ready, query is ready

  SPI_connect();

  // Execute the query
  result = SPI_exec( query, 0 );
  rows = SPI_processed;

  // If the SELECT statement worked, and returned more than zero rows
  if (result == SPI_OK_SELECT && rows > 0)
  {
    // Get the tuple (row) description for the rows
    TupleDesc tupdesc = SPI_tuptable->tupdesc;
    // Get pointer to the tuple table containing the result tuples
    SPITupleTable *tuptable = SPI_tuptable;

    // Loop over each row in the result set (tuple set)
    for( i = 0; i < rows; i++ )
    {
      // Get tuple (row) number i
      HeapTuple tuple = tuptable->vals[i];

      // Store a pointer to the depth value Datum on this row
      datumDepth = SPI_getbinval( tuple, tupdesc, 1, &nullDepth );
      floatDepth = DatumGetFloat4( datumDepth );
      if( nullDepth )
        elog ( ERROR, "NULL depth value on row %i", i );

      if( flipSigns )
        floatDepth *= -1.0;

      // Write the little-endian floatDepth into bigendDepth
      bEnd += 3;
      for( cnt = 0; cnt < 4; cnt++ )
      {
        *bEnd = *lEnd;
        if( cnt < 3 ) {
          lEnd++;
          bEnd--;
        }
      }
      lEnd -= 3;

      // Write the floating point depth value out to the file
      fwrite(&bigendDepth,sizeof(float),1,fpout);
    }
  }

  // Done using the result set
  SPI_finish();

  // Close the output file
  fclose(fpout);

  // CHMOD the file for access by group tportal
  int mode = ( S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH );
  chmod( outfilename, mode );

  // Free up memory
        pfree(tablename);
        pfree(outfilename);

  return 0;
}

