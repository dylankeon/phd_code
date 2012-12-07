#!/usr/bin/perl

### Appendix A:  Conversion of Gridded Bathymetry ASCII (xyz) Datasets to Spatial Database Tables ###

# xyz2sql.pl
#
# This script parses space-delimited text files in xyz or yxz
# format, such as bathymetry data.  The text file will be parsed
# into an SQL file that can be ingested into a PostGIS-enabled
# PostgreSQL database by running a command such as:
#
#    psql .h <host> -d <your_database> -f <sql_output_file>
#
# as long as you have write permission on that database.
#
# This script also adds grid-based x and y values (0 0, 0 1, 0 2,
# etc). It requires a "unit" value to define how many rows in the
# text file constitute a unit (row or column) of the grid.  This is
# dependent upon your data, so you will need to manually take a
# look at your text file to determine this value.  In my case I was
# dealing with text files where a block of 500 text rows had a +/-
# constant latitude, with longitude increasing.  At the end of 500
# rows, the latitude changed to a more-or-less fixed value, and the
# longitude started over at the lowest value.  So, my "unit" value
# was 500.
#
# The script takes a number of command line arguments, which can be
# reviewed by running the script with no arguments.  The script
# creates the necessary table and geometry column, as well as a
# GiST index and indexes on the grid x & y columns. It also does a
# VACUUM ANALYZE on the table.

use strict;
use warnings;

# disable command buffering, so that 'Processing....done' works
$| = 1;

if(scalar(@ARGV) != 8) {
die "
  Usage: xyz2sql.pl <input file> <input format> <unit> <db> <table> <geom> <srid> <output file>\n
  <input file>  Name of input text file (with path if outside current directory)
  <input format>  Must choose one of [xyz|yxz|zyx|xyzxy|xyzyx]
        Note:   xyzxy and xyzyx refer to data like 311 0 580 128.34445 34.28819
                where x and y refer to grid coordinates.
                Grid coordinates MUST be the first xy pair, lat/lon the last xy pair
  <unit> Number of rows that make up a discrete unit (usually defined by latitude)
  <db>  Name of PostgreSQL database, which must be spatially enabled with PostGIS
  <table>  Name of new table (cannot contain a decimal point)
  <geom>  Name of geometry column in the new table
  <srid>  Desired SRID for geometry (-1 == undefined)\n
  <output file>  Name of output sql file (with full path if outside current directory)\n
  Example:
  ./xyz2sql.pl bathy_500.xyz xyz 776 tsunami bathy_500 geom -1 bathy_500.sql
";
}

if($ARGV[1] !~ /xyz|yxz|zyx|xyzxy|xyzyx/) { die "  WARNING: <format> must be one of [xyz|yxz|zyx|xyzxy|xyzyx]!\n  Script did not run!\n"; }
if($ARGV[4] =~ /\./) { die "  WARNING: <table> cannot contain a decimal point!\n  Script did not run!\n"; }

my $infile = $ARGV[0]; #input file
my $format = $ARGV[1]; #whether data is xyz, yxz, etc
my $unit = $ARGV[2]; #how many rows make up a 'unit'
my $db = $ARGV[3]; #db name
my $table = $ARGV[4]; #table name
my $geom = $ARGV[5]; #geometry column
my $srid = $ARGV[6]; #srid number (-1 == undefined)
my $outfile = $ARGV[7]; #output file

my @depth;
my @lat;
my @lon;
my @x;
my @y;
my $x;
my $y;
my $z;

open(IN, $infile) || die "Could not open '$infile' - $!";

my $inCount = 1;
print "\n  => Parsing $infile";
# parse and suck the text file into arrays
while(<IN>) {
        #my $line = $_ unless $_ =~ /^#/;
        my $line = $_;
        chomp($line);
        $_ =~ /^\s*([+-]?\d+\.?\d*[eE]?[+-]?\d*)\s+([+-]?\d+\.?\d*[eE]?[+-]?\d*)\s+([+-]?\d+\.?\d*[eE]?[+-]?\d*)/;
        my $a = sprintf("%.10g", $1);
        my $b = sprintf("%.9g", $2);
        my $c = sprintf("%.6g", $3);
        # NOTE - these are just the formats I've seen so far...
        # more may need to be added
        if($format eq 'xyz') {
                push @lon, $a;
                push @lat, $b;
                push @depth, $c;
        } elsif($format eq 'yxz') {
                push @lon, $b;
                push @lat, $a;
                push @depth, $c;
        } elsif($format eq 'zyx') {
                push @lon, $c;
                push @lat, $b;
                push @depth, $a;
        } elsif($format eq 'xyzyx') {
                $line =~ /^(\d+)\s(\d+)\s(-?\d+\.?\d+?)\s(-?\d+\.\d+)\s(-?\d+\.\d+)/;
                push @x, $1;
                push @y, $2;
                push @lon, $5;
                push @lat, $4;
                push @depth, $3;
        } elsif($format eq 'xyzxy') {
                $line =~ /^(\d+)\s(\d+)\s(-?\d+\.?\d+?)\s(-?\d+\.\d+)\s(-?\d+\.\d+)/;
                push @x, $1;
                push @y, $2;
                push @lon, $4;
                push @lat, $5;
                push @depth, $3;
        }
        if($inCount % 100000 == 0) {print ".";}
        if($inCount % 1000000 == 0) {print $inCount;}
        $inCount++;
}
print "...done\n";

close(IN);

# count latitude values to get a total row count
my $count = scalar(@lat);

open(OUT, "> $outfile") || die "Could not open '$outfile' - $!";

# create table - currently creates x and y cols no matter what -
# we add x & y values below if they don't exist
my $sql1;
#if($format !~ /xyzxy|xyzyx/) {
#       $sql1 = "CREATE TABLE $table (id int, depth int);";
#} else {
        $sql1 = "CREATE TABLE $table (id int, x smallint, y smallint, depth numeric);";
#}
print OUT "$sql1\n";

# create geometry column
#print "  => Adding geometry column ...";
print OUT "SELECT AddGeometryColumn('$table', '$geom', $srid, 'POINT', 2);\n";

# insert data
print "  => Printing $count rows to $outfile";
# x,y needs to start at 1,1 to work with ARSC's Fortran code
my $xpos = 0; #initialize at 0, will go to 1 at next increment
my $ypos = 1; #initialize at 1
for(my $i = 0; $i < $count; $i++) {
        if($i != 0 && $i % 100000 == 0) {print ".";}
        if($i != 0 && $i % 1000000 == 0) {print $i;}
        my $id = $i + 1; #so that id column begins at 1
        my $sql2;

        if($i != 0 && $i % $unit == 0) {
                $xpos = 0; #reset at each $unit rows - will go to 1 at next increment
                $ypos++; #increment 1 for each $unit rows
        }
        $xpos++; #increment 1 for every row

        if($format !~ /xyzxy|xyzyx/) {
                $sql2 = "INSERT INTO $table (id, x, y, depth, $geom) VALUES ($i, $xpos, $ypos, $depth[$i], GeometryFromText('POINT($lon[$i] $lat[$i])', $srid));";
        } else {
                $sql2 = "INSERT INTO $table (id, x, y, depth, $geom) VALUES ($i, $depth[$i], $x[$i], $y[$i], GeometryFromText('POINT($lon[$i] $lat[$i])', $srid));";
        }
        print OUT "$sql2\n";
}
print OUT "COMMIT;\n";
print "...done\n";

# create GiST index
#print "  => Creating GiST index $table\_index ...";
print OUT "CREATE INDEX $table\_gist_index ON $table USING gist ($geom gist_geometry_ops);\n";
print OUT "CREATE INDEX $table\_x_index ON $table (x);\n";
print OUT "CREATE INDEX $table\_y_index ON $table (y);\n";

# vacuum analyze to update table stats
#print "  => Performing VACUUM ANALYZE on table ...";
print OUT "VACUUM ANALYZE $table;\n";

close(OUT);

print "\n  Job finished\n\n";

