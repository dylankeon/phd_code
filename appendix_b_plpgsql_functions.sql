/*** Appendix B:  PostgreSQL Database Functions for Calculating Parameters of Gridded Bathymetry Datasets ***/

--
-- This function calculates maximum allowable time step for
-- each gridded dataset.
-- It is called via max_time_step()
--
-- * delta T = (1855 * cos(radians(max_lat))*spacing/60) / sqrt(2 * g * abs(max_depth))
-- * g = gravitational constant (9.8)
-- * use abs(max_depth) because our depths might be stored as negative values whereas most modelers store depths as positive values
-- * need to use radians() with cos()
CREATE OR REPLACE FUNCTION max_time_step()
RETURNS VOID AS '
DECLARE
  max_lat NUMERIC;
  max_depth NUMERIC;
  spc NUMERIC;
  g CONSTANT FLOAT := 9.8;
  m CONSTANT INTEGER := 1855;
  result NUMERIC;
  curs1 refcursor;
  row RECORD;
BEGIN
  FOR row in SELECT db_table FROM input_grids WHERE max_allowable_time_step IS NULL LOOP
	
    RAISE NOTICE ''processing %'', row.db_table;
	
    OPEN curs1 FOR EXECUTE ''SELECT max(y(geom)) FROM '' || quote_ident(row.db_table);
    FETCH curs1 into max_lat;
    CLOSE curs1;

    OPEN curs1 FOR EXECUTE ''SELECT min(depth) FROM '' || quote_ident(row.db_table) || '' where depth > -30000 '';
    FETCH curs1 into max_depth;
    CLOSE curs1;

    OPEN curs1 FOR EXECUTE ''SELECT spacing FROM input_grids WHERE db_table = '''''' || quote_ident(row.db_table) || '''''''';
    FETCH curs1 into spc;
    CLOSE curs1;
	
    result := (m * cos(radians(max_lat))*(spc/60)) / sqrt(2 * g * abs(max_depth));
	
    EXECUTE ''UPDATE input_grids SET max_allowable_time_step = '' || quote_literal(result) || '' WHERE db_table = '''''' || quote_ident(row.db_table) || '''''''';

  END LOOP;
  RETURN;
END;
' LANGUAGE 'plpgsql';


--
-- This function calculates the number of time steps required to
-- cross the max grid extent, for each gridded dataset.
-- It is called via num_time_steps()
--
-- * following gives us distance in meters:
--    y_dist = (maxy - miny) * 60 * 1861
--    x_dist = (maxx - minx) * 60 * 1855 * cos(radians(miny))
-- * total_time = max_dist/sqrt(g * abs(avg_depth))
-- * use abs(avg_depth) because our depths might be stored as negative values whereas most modelers store depths as positive values
-- * need to use radians() with cos()
CREATE OR REPLACE FUNCTION num_time_steps()
RETURNS VOID AS '
DECLARE
    minx NUMERIC;
    miny NUMERIC;
    maxx NUMERIC;
    maxy NUMERIC;
    max_dist NUMERIC;
    avg_depth NUMERIC;
    result NUMERIC;
    y_dist NUMERIC;
    x_dist NUMERIC;
    ym CONSTANT INTEGER := 1861;
    xm CONSTANT INTEGER := 1855;
    g CONSTANT FLOAT := 9.8;
    row RECORD;
    curs1 REFCURSOR;
BEGIN
    FOR row in SELECT db_table FROM input_grids WHERE time_to_cross_max_domain IS NULL LOOP

        RAISE NOTICE ''PROCESSING %'', row.db_table;

        OPEN curs1 FOR EXECUTE ''SELECT min(x(geom)) FROM '' || quote_ident(row.db_table);
        FETCH curs1 into minx;
        CLOSE curs1;
        --RAISE NOTICE ''minx = %'', minx;

        OPEN curs1 FOR EXECUTE ''SELECT min(y(geom)) FROM '' || quote_ident(row.db_table);
        FETCH curs1 into miny;
        CLOSE curs1;
        --RAISE NOTICE ''miny = %'', miny;

        OPEN curs1 FOR EXECUTE ''SELECT max(x(geom)) FROM '' || quote_ident(row.db_table);
        FETCH curs1 into maxx;
        CLOSE curs1;
        --RAISE NOTICE ''maxx = %'', maxx;

        OPEN curs1 FOR EXECUTE ''SELECT max(y(geom)) FROM '' || quote_ident(row.db_table);
        FETCH curs1 into maxy;
        CLOSE curs1;
        --RAISE NOTICE ''maxy = %'', maxy;

        OPEN curs1 FOR EXECUTE ''SELECT avg(depth) FROM '' || quote_ident(row.db_table) || '' where depth > -30000 '';
        FETCH curs1 into avg_depth;
        CLOSE curs1;
        --RAISE NOTICE ''avg_depth = %'', avg_depth;

        y_dist = (maxy - miny) * 60 * ym;
        x_dist = (maxx - minx) * 60 * xm * cos(radians(miny));
        --RAISE NOTICE ''y_dist = %'', y_dist;
        --RAISE NOTICE ''x_dist = %'', x_dist;

        IF y_dist > x_dist
        THEN
            result := y_dist/sqrt(g * abs(avg_depth));
        ELSE
            result := x_dist/sqrt(g * abs(avg_depth));
        END IF;
        result := round(result,0);
        RAISE NOTICE ''  result = %'', result;

        EXECUTE ''UPDATE input_grids SET time_to_cross_max_domain = '' || quote_literal(result) || '' WHERE db_table = '''''' || quote_ident(row.db_table) || '''''''';

    END LOOP;
    RETURN;
END;
' LANGUAGE 'plpgsql';


