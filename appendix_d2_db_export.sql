/*** Appendix D:  Exporting and Packaging Selected Grid Extent ***/

CREATE OR REPLACE FUNCTION output_bin_dfile( text, text, int4, int4, int4, int4, boolean )
RETURNS int4
AS '/private/share/tcp/bin/libpgoutdfile.so'
LANGUAGE 'c';

