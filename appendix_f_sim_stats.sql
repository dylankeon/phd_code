/*** Appendix F:  Database Function for Automatically Calculating Simulation Run Summary Statistics ***/

/*
  This PL/pgSQL function creates a stats table containing the number of
  simulated people alive at each time step index. The table is given the
  same name as the data table, with '_stats' appended.

  Inputs:
    maintbl: name of the table containing the data for the time series run
    ts: total number of time steps simulated (1-based)

  Example usage:
    SELECT create_stats_table('job_151', 500);
*/

CREATE OR REPLACE FUNCTION create_stats_table(maintbl VARCHAR, ts INTEGER)
  RETURNS VOID
AS $$

DECLARE
  curs1 refcursor;
  max INTEGER;
  ct INTEGER;
  endct INTEGER;
  newtbl VARCHAR := ($1 || '_stats');

BEGIN

  IF EXISTS (
      SELECT *
      FROM   pg_catalog.pg_tables
      WHERE  schemaname = 'public'
      AND    tablename  = quote_ident(newtbl)
     )
  THEN
     RAISE NOTICE 'ERROR: Table public.%', newtbl || ' already exists. Exiting now.';
     EXIT;
  ELSE
     EXECUTE 'CREATE TABLE ' || quote_ident(newtbl) || ' (tidx int, alive int)';
     RAISE NOTICE 'Created table %', newtbl;
  END IF;

  -- select max time step index contained in main data table (can be < ts)
  OPEN curs1 FOR EXECUTE 'SELECT max(tidx) FROM ' || quote_ident(maintbl);
    FETCH curs1 INTO max;
    CLOSE curs1;

  -- select number of people living at end of simulation (time index in
  -- main data table may end prior to full ts value, so this 'endct' var
  -- is used to fill in the count for those final values)
  OPEN curs1 FOR EXECUTE 'SELECT count(id) FROM ' || quote_ident(maintbl) || ' WHERE alive IS NULL';
    FETCH curs1 INTO endct;
    CLOSE curs1;

  FOR i IN 0..ts-1 LOOP
    IF max+1 <= i THEN
      EXECUTE 'INSERT INTO ' || quote_ident(newtbl) || ' VALUES (' || i || ', ' || endct || ')';
    ELSE
      OPEN curs1 FOR EXECUTE 'SELECT count(id) FROM ' || quote_ident(maintbl) || ' WHERE tidx = ' || i || ' AND alive IS TRUE OR alive IS NULL';
        FETCH curs1 INTO ct;
        CLOSE curs1;
      EXECUTE 'INSERT INTO ' || quote_ident(newtbl) || ' VALUES (' || i || ', ' || ct || ')';
    END IF;
  END LOOP;

  RAISE NOTICE 'Finished';

END;
$$
LANGUAGE 'plpgsql';

