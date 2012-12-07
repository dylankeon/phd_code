/*** Appendix E:  Casualty Calculation Code for the Simulation Model ***/

#include "CasualtyCalc.h"

void BuildVisuals(DataOutBlock<GByte> *inBuff, DataOutBlock<GByte> *outBuff)
{
  int i,j,k;
  int v;
  int idx;
  GByte *buf = inBuff->GetSliceAt(0); //Both genders
  for (i=0;i<Row;i++){
    for(j=0;j<Col;j++){
      v=buf[i*Col+j];
      if (v == 0) {
        for (k=0;k<3;k++)
          outBuff->SetCellAt(j,i,k,(GByte)Zero[k]);
      }
      else {
        idx = (int)floor((float)v/10.); // color map index
        if (idx > 15) idx = 15;
        for (k=0;k<3;k++)
          outBuff->SetCellAt(j,i,k,(GByte)HSDColorMap[idx][k]);
      }
    }
  }
}

bool WriteOutFile(DataOutBlock<GByte> *DB, char *filename)
{
  char *finalName = new char[strlen(filename) + strlen(oPath) + 2]; // 1 for \0 and 1 for '/' just in case 
  strcpy(finalName,oPath);
  if (oPath[strlen(oPath-1)]!='/')
    strcat(finalName,"/");
  strcat(finalName,filename);

  TiffByteWriter *tw = TiffWriterFactory::instanceOf(finalName,ex,DB);
  tw->setNoDataValue(0.);
  tw->WriteTiff();
  delete tw; // must do this explicitly or it won't get written
  delete finalName;
  return (true);
}

bool WriteOutFile(DataOutBlock<GInt16> *DB, char *filename)
{
  char *finalName = new char[strlen(filename) + strlen(oPath) + 2]; // 1 for \0 and 1 for '/' just in case 
  strcpy(finalName,oPath);
  if (oPath[strlen(oPath-1)]!='/')
    strcat(finalName,"/");
  strcat(finalName,filename);

  TiffShortWriter *tw = TiffWriterFactory::instanceOf(finalName,ex,DB);
  tw->setNoDataValue(0.);
  tw->WriteTiff();
  delete tw; // must do this explicitly or it won't get written
  delete finalName;
  return (true);
}

bool ReadPeopleFile()
{
  FILE *fp;
  char buf[HSDMAXBUF];
  float x,y;
  float sx,sy;
  int   gid;
  float t;
  int srid;
  Location *l;
  Location *srl;
  int id;
  int idx = 0;
  Location S;

  assert((fp=fopen(People_infile,"r"))!=NULL);

  fgets(buf,HSDMAXBUF,fp);
  sscanf(buf,"%d %d\n",&NumPeople,&srid);
  people = new Person[NumPeople];

  fgets(buf,HSDMAXBUF,fp);
  while (!feof(fp)){
    sscanf(buf,"%d\t%f\t%f\t%f\t%f\t%d\t%f\n",&id,&y,&x,&sy,&sx,&gid,&t);
    l = new Location(x,y,srid);
    srl = new Location(sx,sy,srid);
    if ((ex->World2Screen(*l,S))==true) {
      people[idx].Set_Person(id,l,pt);
      RN->RoutePerson(&people[idx],srl,gid,t);
      idx++;
    }
    fgets(buf,HSDMAXBUF,fp);
  } 

  printf("Read in %d people, kept %d\n",NumPeople,idx);
  NumPeople=idx;
  return (true);
}


bool ReadStructureFile()
{
  FILE *fp;
  char buf[HSDMAXBUF];
  float x,y;
  int srid;
  Location l;
  int gid;
  int hc;
  int idx = 0;
  Location S;

  assert((fp=fopen(Structure_infile,"r"))!=NULL);

  fgets(buf,HSDMAXBUF,fp);
  sscanf(buf,"%d %d\n",&NumStructures,&srid);
  structures = new Structure[NumStructures];

  fgets(buf,HSDMAXBUF,fp);
  while (!feof(fp)){
    sscanf(buf,"%d\t%d\t%f\t%f\n",&gid,&hc,&y,&x);
    l.Set_Location(x,y,srid);
    if ((ex->World2Screen(l,S))==true)
      structures[idx++].setStructure(gid,hc,l);
    fgets(buf,HSDMAXBUF,fp);
  } 
  printf("Read in %d structures, kept %d\n",NumStructures,idx);
  NumStructures=idx;
  return (true);
}

void
usage(void)
{
  printf("av[1] - input H Series file\n"\
 "av[2] - input U Series file name\n"\
 "av[3] - input V Series file name\n"\
 "av[4] - input Bathy-Topot file name\n"\
 "av[5] - extent file name\n"\
 "av[6] - People file name\n"\
 "av[7] - Structures file name\n"\
 "av[8] - Number of time slices to iterate across\n"\
 "av[9] - Path to directory where output products will be dumped\n"\
 "av[10] - When to start people running (i.e. which time index)\n"\
 "av[11] - Output table name\n"\
 "av[12] - Road Network name\n"\
"\n\n");
exit(0);
}

bool InitializeOutputDir(void)
{
  DIR *dirp;

  if ((dirp=opendir(oPath))!=NULL)
    closedir(dirp);
  else {
    return((mkdir(oPath,0755)==0 ? true:false));
  }
  return(true);
}

 //av[1] - input H Series file\n"\
 //av[2] - input U Series file name\n"\
 //av[3] - input V Series file name\n"\
 //av[4] - input Bathy-Topot file name"\
 //av[5] - extent file name\n"\
 //av[6] - People file name\n"\
 //av[7] - Structures file name\n"\
 //av[8] - Number of time slices to iterate across\n"\
 //av[9] - Path to directory where output products will be dumped\n"\
 //av[10] - When to start people running (i.e. which time index)\n"\

bool Initialize(int ac, char **av)
{
  if (ac < 8) usage();

  H_infile = strdup(av[1]); 
  U_infile = strdup(av[2]); 
  V_infile = strdup(av[3]); 
  BT_infile = strdup(av[4]); 
  extent_infile = strdup(av[5]);
  People_infile = strdup(av[6]);
  Structure_infile = strdup(av[7]);
  Time = atoi(av[8]);
  oPath = strdup(av[9]);
  whenToRun = atoi(av[10]);
  Output_Table_Name = strdup(av[11]);
  Road_infile = strdup(av[12]);
  oPrefix = strdup("");

  assert(InitializeOutputDir());

  ex = new Extent(extent_infile);
  Row = ex->get_Row();
  Col = ex->get_Col();
  pm = new PeopleMover(ex);
  RN = new RoadNetwork(Road_infile);

  dbU = new DataBlock<float>(Row,Col,Time,U_infile);
  dbV = new DataBlock<float>(Row,Col,Time,V_infile);
  dbH = new DataBlock<float>(Row,Col,Time,H_infile);
  dbBT = new DataBlock<float>(Row,Col,1,BT_infile);

  pt = new PopTable();

  ReadPeopleFile();

  dbpr = new DBPopRecorder(Output_Table_Name);

  return (true);
}

char *MakeFilename(char *base, int num)
{
  char *name = new char[strlen(base)+5+strlen(oPrefix)+1+4]; //length of base + 5 digits + length of prefix + 4 for .tif + 1 for null
  sprintf(name,"%s%s%05d.tif",oPrefix,base,num);
  return (name);
}

void
CalcCasualty(Person *p,int sx, int sy, float *gh, float *gu, float *gv, float *gbt, int pidx)
{
  PopRow *pr = p->get_ptr();

  float u;
  float v;
  float h;
  float speed;
  float tol=1e-6;

  if (isnan(gh[sy*Col+sx])) return; // no water...no casualty
  h = gh[sy*Col+sx]/100.-(gbt[sy*Col+sx]*-1.);
  if (h < tol) return; // water is so low it's in the noise
  if (h < p->get_height()) return; // the person is on something (bridge) above the water

  if (isnan(gu[sy*Col+sx])) u = 0.; // no U speed;
  else  u = gu[sy*Col+sx]/100.; // convert cm/sec to m/sec
  if (isnan(gv[sy*Col+sx])) v = 0.; // no V speed;
  else v = gv[sy*Col+sx]/100.; // convert cm/sec to m/sec

  speed = sqrt(u*u+v*v);

  if (speed < tol) return;  //water speed ~= 0...no casualty

  float a = pr->get_a();
  float b = pr->get_b();
  float c = pr->get_c();
  float d = pr->get_d();
  float e = pr->get_e();
  float f = pr->get_f();
  float hb = pr->get_hb();
  float hc = pr->get_hc();
  float hg0 = pr->get_hg0();
  float x = pr->get_x();
  float w = pr->get_weight()*9.81;
  float A,Ay0,V,um,uf,minu;
  float wsv;
  
  if (h < hb){
    A = a*h+(b-a)*((h*h)/(2.*hb));
    Ay0 = ((a/2.)*(h*h)+((b-a)/(3*hb))*powf(h,3.));
    V = h * 3.1415926 * ((a + (b - a) * (h/hb))/2.) * ( (d + (e-d) * (h/hb))/2.0);
  } else {
    A = ((a + b)/2.)*hb+b*(h-hb)+((c-b)/(2.*(hc-hb)))*(h-hb)*(h-hb);
    Ay0 = ((a/2.)*hb*hb+((b-a)/3.)*hb*hb+(1./(6.*(hb-hc)))*(3*c*h*h*hb-(2*b+c)*powf(hb,3.)+3*b*hb*hb*hc+h*h*(2.*(b-c)*h-3.*b*hc)));
    V = hb * 3.1415926 * ((a + (b - a) * (h/hb))/2.) * ( (d + (e-d) * (h/hb))/2.0) + (h-hb)*3.1415926 * ((b+(c-b) * ((h-hb)/(hc-hb)))/2.) * ((e+(f-e)*((h-hb)/(hc-hb)))/2.0);
  } 

  if (w < (sigma*V)) {
    Location currentPersonLocation = p->get_location();
    p->set_dead(true);
    return;
  }

  wsv = w-sigma*V;
  um = sqrt((2.*wsv*x)/(Cd*Density*Ay0));
  uf = sqrt((2.*ff*wsv)/(Cd*Density*A));
  minu = HSDMIN((um),(uf));

  if ((speed/minu) < 1.) {
    return;
  } else {
    p->set_dead(true);
  }

  return;
}

void RunSimulation(void)
{
  int t;
  int p;
  int TotalDead=0;
  int DeadThisRound;
  Location currentPersonLocation;
  Location  Screen;
  char  *deadTimeSliceFileName;
  char  *livingTimeSliceFileName;
  char  *waterTimeSliceFileName;
  char  *cummDeadFileName;
  char  *visualLivingFileName;
  char  *visualDeadFileName;
  int   x,y;
  float *gh,*gu,*gv,*gbt;
  int i;

  gbt = dbBT->GetSliceAt(0);

  printf("Starting Simulation with %d people\n",NumPeople);

  for(t = 0;t < Time; t++){
    if (TotalDead >= NumPeople) break;

    DeadThisRound=0;
    gh = dbH->GetSliceAt(t);
    gu = dbU->GetSliceAt(t);
    gv = dbV->GetSliceAt(t);
    printf("Calculating casualties for time index %d...",t);

    for(p = 0; p < NumPeople; p++){
      if (people[p].is_dead()) {
        continue;
      }

      if (people[p].is_safe()) continue;

      currentPersonLocation = people[p].get_location();
      if((ex->World2Screen(currentPersonLocation,Screen))==false){
        people[p].set_safe(true);
        continue;
      }
      x=(int)Screen.get_x();
      y=(int)Screen.get_y();

      CalcCasualty(&people[p],x,y,gh,gu,gv,gbt,p);

      dbpr->addRow(p,&people[p],t);

      if (people[p].is_dead()) {
        DeadThisRound++;
        TotalDead++;
      } else {
        if (t >= whenToRun)
          pm->MovePerson(&people[p]);
      }
    }
    printf("%d %s died\n",DeadThisRound,(DeadThisRound == 1 ? "person has":"people have"));
  }

  dbpr->Flush();

  int deadStats=0;
  int liveStats=0;

  for(p = 0; p < NumPeople; p++){
    if (people[p].is_dead()) deadStats++;
    else liveStats++;
  }

  printf("Total People: %d\n\t Dead: %d (%5.2f%%)\n\tAlive: %d (%5.2f%%)\n\n\n",
          NumPeople,deadStats,((float)deadStats/(float)NumPeople)*100.,
          liveStats,((float)liveStats/(float)NumPeople)*100.);
}

int
main(int ac, char **av)
{
  // Load global variables from CMD line or issue usage
  assert(Initialize(ac,av));
  RunSimulation();
}

