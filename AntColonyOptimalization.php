<?php
//Algorytm znajduje heurystyczne rozwiazanie problemu komiwojazera dla grafu niepelnego,
//celem jest polaczenie wybranej ilosci punktow (targetow) jak najkrotsza sciezka,
//przy czym liczba wszystkich punktow jest z reguly wieksza od targetow (nie szuka trasy przez wszystkie miasta tylko przez targety ktore z reguly nie maja bezposredniego polaczenia)

//Dzialanie: w kazdej iteracji przechodzi podawana liczba mrowek, feromony sa aktualizowane po przejsciu WSZYSTKICH mrowek w danej iteracji (nie co przejscie pojedynczej mrowki).
//Mrowki zapamietuja trase przebyta, ktora jest resetowana gdy target zostanie odwiedzony. Mrowka nie moze sie cofnac tylko do punktu z ktorego bezposrednio przyszla,
//gdy jednak odwiedzi punkt ktory juz wczesniej odwiedzila resetowana jest jej trasa od tego punktu
//(nie ma petli miedzy przejsciam pomiedzy targetami, mrowka moze jednak odwiedzac punkty ktore wczesniej odwiedzala przed ostatnio odwiedzanym targetem)

class AntColonyOptimalization {
  //dane wejsciowe
  private $graph; // odleglosc miedzy punktami, [i][j] = distance
  private $targetPoints; // punkty dowozu
  private $antPopulationSize; // liczba mrowek w iteracji
  private $alpha; //wpływ pozostawionego feromonu
  private $beta; //wpływ odleglosci miedzy punktami na wyliczone prawdopodobienstwo
  private $evapoRate; //współcznnik odparowania

  //dodatkowe atrybuty do obliczen
  private $iter; //numer przypisany aktualnej iteracji
  private $antNumber; //numer przypisany aktualnej mrowce
  private $numberOfIter = 10; //liczba iteracji algorytmu
  private $q3 = 50; //wspolczynnik dawki zostawionego feromonu
  private $p0 = 0.1; //startowy poziom feromonow
  private $best = PHP_INT_MAX; //najlepsza dlugosc trasy jaka znalazla mrowka
  private $bestAnt = 0; //indeks najlepszej mrowki
  private $bestIter = 0; //indeks najlepszej iteracji

  //dla mrowek i iteracji
  private $antsVisitedPoints = []; //(wszystkie) odwiedzone punkty przez mrowke, [k][iteracja] = [values ...], k - mrowka
  private $distanceMatrix = []; //tablica dlugosci (czasu) całkowitej znalezionej trasy [k][iter] = sum, k - mrowka

  //atrybuty dla danej mrowki (resetowane co mrowke)
  private $antNextPoints = []; //punkty ktorych mrowka jeszcze nie odwiedzila, a do ktorych prowadzi droga z obecnego punktu, [j1, j2 ...]
  private $antPossibilityWithNeighbours = []; // prawdopodobienstwo wybrania drogi przez mrowke do swoich mozliwych sasiadow, [j] = value
  private $antPointsToEnd = []; //punkty ktore mrowka musi jeszcze odwiedzic
  private $currentPoint; //obecnie przetwarzany punkt
  private $antPreviusPoint; //poprzednio odwiedzony punkt przez mrowke
  private $antTabuPoints = []; // punkty ktora mrowka odwiedzila od ostatniego targetu, [pt, pt, pt, pt, pt ...] - gdy ktorys z tych punktow zostanie ponownie odwiedzony resetowana jest trasa od tego punktu

  //po iteracji resetuje
  private $bufPheromone = []; //tablica buforowa wartosci feromonu znalezionego przez wszystkie mrowki w danej iteracji $bufPheromone [i][j][k] = value, k - mrowka

  private $loopsCount = 0;

  public function __construct($graph, $targetPoints, $antPopulationSize, $alpha, $beta, $evapoRate){
    $this->graph = $graph;
    $this->targetPoints = $targetPoints;
    $this->antPopulationSize = $antPopulationSize;
    $this->alpha = $alpha;
    $this->beta = $beta;
    $this->evapoRate = $evapoRate;
  }

  //liczy prawdopodobienstwo wybrania sciezki z $source do $dest, na podstawie wszystkich bezposrednich sasiadow oraz poziomu feromonu na danym odcinku
  private function calcPossibility($source, $dest) {
    $t = $this->iter; // aktualna iteracja
    $sum = 0; // zsumowany (dla wszsytkich sasiadow) wspolczynnik
    foreach ($this->antNextPoints as $v) {
      $sum += ($this->pheromone[$source][$v]**$this->alpha)*((1/$this->graph[$source][$v])**$this->beta);
    }
    $this->antPossibilityWithNeighbours[$dest] = ($this->pheromone[$source][$dest]**$this->alpha)*((1/$this->graph[$source][$dest])**$this->beta)/$sum;
  }

  //wybiera losowo na podstawie warzonego prawawdopodobienstwa nastepny punkt
  private function chooseNextPoint() : int {
    $randomNumber = random_int(0, PHP_INT_MAX-1)/PHP_INT_MAX;
    $bufPossibility = [];
    $offset = 0;

    foreach ($this->antPossibilityWithNeighbours as $key => $value) {
      $offset += $value;
      $bufPossibility[$key] = $offset;
    }
    foreach ($bufPossibility as $key => $value) {
      if($randomNumber < $value){
        return $key;
      }
    }
  }

  private function calcPossibleNextPoints(){
    $this->antNextPoints = array();

    foreach ($this->graph[$this->currentPoint] as $j => $weight) {
      if($j != $this->antPreviusPoint){
        array_push($this->antNextPoints, $j);
      }
    }
  }

  //(bufor) liczy nowym poziom feromonu na trasie znalezionej przez mrowke i zapisuje do bufora
  private function calcBuffPheromone() {
    $t = $this->iter; //aktualna iteracja
    $allFoundedPoints = $this->antsVisitedPoints[$this->antNumber][$t]; //punkty przez ktore przeszla mrowka
    $i = array_shift($allFoundedPoints);

    while(!empty($allFoundPoints)){ // oblicz dawke feromonow pozostawioną przez mrowke
      $j = array_shift($allFoundPoints);
      $this->bufPheromone[$i][$j][$this->antNumber] = ($this->q3)/($this->graph[$i][$j]);
      $i = $j;
    }
  }

  //aktualizuje poziom feromonow po przejsciu przystkich mrowek
  private function updatePheromone() {
    for ($k=0; $k < $this->antPopulationSize; $k++) {
      foreach ($this->pheromone as $i => $value) { //zaktualizuj poziom feromonow
        foreach ($value as $j => $weight) {
          $this->pheromone[$i][$j] = (1 - $this->evapoRate)*$this->pheromone[$i][$j] + (isset($this->bufPheromone[$i][$j][$k]) ? $this->bufPheromone[$i][$j][$k] : 0);
        }
      }
    }
  }

  //ustawia poziom feromonow na p0 dla wszystkich tras (inicjalizacja algorytmu)
  private function initialPheromone() {
    foreach ($this->graph as $i => $value) {
      foreach ($value as $j => $weight) {
        $this->pheromone[$i][$j] = $this->p0;
      }
    }
  }

  //sprawdza czy warunek dla danego punktu jest spelniony
  private function stoppingCondition(){
    if(empty($this->antPointsToEnd)){ //byl to ostatni punkt
      return false;
    }
    if(in_array($this->currentPoint, $this->antPointsToEnd)) { //jesli punkt odwiedzony jest w tablicy targetow
        unset($this->antPointsToEnd[array_search($this->currentPoint, $this->antPointsToEnd)]); // usun ten punkt z listy punktow do odwiedzenia
        $this->antTabuPoints = array(); //resetuj historie punktów
        return true;
    }
    return true;
  }

  //inicjacja dla kazdej mrowki. Kasuje $antPreviusPoint, resetuje $antTabuPoints, ustawia $antPointsToEnd, inicjuje $this->antsVisitedPoints[k][iter] i $this->distanceMatrix[k][iter]
  private function InitiationForAnt(){
    $this->antPreviusPoint = PHP_INT_MAX;
    $this->antTabuPoints = array();
    $this->antPointsToEnd = array_merge(array(), $this->targetPoints);
    $this->antsVisitedPoints[$this->antNumber][$this->iter] = [];
    $this->distanceMatrix[$this->antNumber][$this->iter] = 0;
  }

  //sprawdza czy punkt byl juz odwiedzony, jesli tak to cofa odwiedzana trase do tego punktu
  private function checkIfWasVisited(){
    if(in_array($this->currentPoint, $this->antTabuPoints)){
      $visited = $this->antsVisitedPoints[$this->antNumber][$this->iter];

      $lengthToDelet = array_search($this->currentPoint, array_reverse($this->antTabuPoints));

      for ($n=0; $n < $lengthToDelet-2; $n++) {
        array_pop($this->antsVisitedPoints[$this->antNumber][$this->iter]);
        array_pop($this->antTabuPoints);
      }
      $this->currentPoint = array_pop($this->antsVisitedPoints[$this->antNumber][$this->iter]);
      array_pop($this->antTabuPoints);

      $this->loopsCount++;
    }
  }

  private function calcDistance(){
    $allPoints = $this->antsVisitedPoints[$this->antNumber][$this->iter];
    $i = array_shift($allPoints);

    while(!empty($allPoints)){
      $j = array_shift($allPoints);
      $this->distanceMatrix[$this->antNumber][$this->iter] += $this->graph[$i][$j];
      $i = $j;
    }
  }

  // private function removeLoops(){
  //   $parts = []; //part wchich leads from one target to another;
  //   $from = 0;
  //   $to = 0;
  //   $pointOrder = [];
  //   $allPoints = $this->antsVisitedPoints[$this->antNumber][$this->iter][$key];
  //   $len = count($allPoints);
  //   $pointsRemeining = $this->targetPoints;
  //
  //   $key=0
  //   while(!empty($pointsRemeining)){
  //     $key++;
  //     if(in_array($allPoints[$key], $pointsRemeining)){ //szukaj punktu ktory jest w target, jesli go znaleziono
  //       unset($pointsRemeining[array_search($allPoints[$key], $this->antPointsToEnd)]); //usun go z remaining
  //       $from = $key; //ustaw $from na obecny klucz
  //
  //       if(!empty($pointsRemeining)){
  //         $sec_key=$key;
  //         while(1){ //zacznij szukac od tego punktu
  //           $sec_key++;
  //           if($allPoints[$key] == $allPoints[$sec_key])){ //jesli znaleziono ten sam punkt to
  //             $from = $sec_key; //ustaw $from na ten punkt
  //           }
  //           elseif (in_array($allPoints[$sec_key], $pointsRemeining)) { //jesli napotkano na punkt nastepny to
  //             $to = $sec_key; //ustaw $to na ten klucz
  //             $key = $sec_key - 1; //pszesun key do obecnego punktu
  //             array_push(array_slice($allPoints, $from, $to - $from), $pointOrder);
  //             break: // wyjdz z petli
  //           }
  //         }
  //       }
  //
  //     }
  //   } //koniec !empty($pointsRemeining)
  //
  //   foreach ($pointOrder as $singlePath) {
  //     $pointsVisited = [];
  //     $start = 0;
  //     $end = 0;
  //
  //     foreach ($singlePath as $pt) {
  //       $withoutLoop = [];
  //       if(in_array($pt, $pointsVisited)){
  //         array_search()
  //       }
  //     }
  //   }
  //
  // }

  public function runAlgorithm() : array {
    $this->initialPheromone(); //ustaw feromony na poziom startowy

    for ($it=0; $it < $this->numberOfIter; $it++) { //petla iteracji
      $this->iter = $it;
      $this->bufPheromone = array(); //resetuje poziom feromonow dla bufforowej tablicy

      for ($ant=0; $ant < $this->antPopulationSize; $ant++) { //uruchamiam populacje mrowek dla danej iteracji
        $this->antNumber = $ant;
        $this->InitiationForAnt(); //inicjacja dla nowej mrowki
        $this->currentPoint = 0; //ustawienie punktu starowego mrowek na [0,0]

        while($this->stoppingCondition()) { //licz trase dla mrowki
          array_push($this->antsVisitedPoints[$this->antNumber][$this->iter], $this->currentPoint);

          $this->calcPossibleNextPoints(); //oblicz mozliwosci dla nastepnej mrowki
          $this->antPossibilityWithNeighbours = array(); //zresetuj prawdopodobienstwo dla mozliwych punktow
          foreach ($this->antNextPoints as $possiblePoint) { //dla kazdego mozliwego nowego punktu oblicz prawdopodobienstwo
            $this->calcPossibility($this->currentPoint, $possiblePoint);
          }
          $this->antPreviusPoint = $this->currentPoint; //nadpisz poprzedni punkt
          $this->currentPoint = $this->chooseNextPoint(); //wylosuj nastepny punkt
          array_push($this->antTabuPoints, $this->antPreviusPoint);

          $this->checkIfWasVisited();
        }
        $this->calcBuffPheromone();
        $this->calcDistance();
      }
      $this->updatePheromone();

      for ($a=0; $a < $this->antPopulationSize; $a++) {
        if($this->distanceMatrix[$a][$this->iter] < $this->best){
          $this->best = $this->distanceMatrix[$a][$this->iter];
          $this->bestAnt = $a;
          $this->bestIter = $this->iter;
        }
      }
    }
    echo 'bestAnt: ' . $this->bestAnt;
    echo 'bestIter: ' . $this->bestIter;
    echo 'loops: ' . $this->loopsCount;

    return $this->antsVisitedPoints[$this->bestAnt][$this->bestIter];
  }

}
?>
