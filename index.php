<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Inzynieria oprogramowania projekt</title>
  <link rel="stylesheet" type="text/css" href="normalize.css">
  <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
  <button type="button" onclick="generate()">GENERATE</button><br />
  <?php
  include 'AntColonyOptimalization.php';

  $N = 10; //grid demension [NxN]
  $n = 5; //number of points
  $grid[] = [];

  $myfile = fopen("grid.txt", "r") or die("Unable to open file!");
  $contents = fread($myfile, filesize("grid.txt"));
  foreach (explode("\r\n\r\n", $contents) as $key => $value) {
    foreach (explode("\r\n", $value) as $k => $v){
      foreach (explode(" ", $v) as $ke => $va){
        $grid[$key][$ke][$k] = $va;
      }
    }
  }


  function pointGenerator(int $n) : array{
    //generates $n random points (x, y) = ($points[$i][0], $points[$i][1])
    global $N;
    $points = [];
    for($i = 0; $i < $n; $i++){
      $x = rand(0, $N-1);
      $y = rand(0, $N-1);
      if(($i > 0 && in_array(array($x, $y), $points)) || $x + $y == 0){ //if point was already picked or is (0, 0) than choose again
        $i--;
      }
      else {
        $points[$i] = [$x, $y];
        echo "(" . $points[$i][0] . ", " . $points[$i][1] . ") ";
      }
    }

    return $points;
  }

  function pointToGraph(array $pt) : int{
    global $N;
    $tmp = $N*$pt[0] + $pt[1];
    return $tmp;
  }

  function allPathfind(Dijkstra $Dij, array $graph, array $points) : array{
    global $N;
    $n = count($points);
    $tmp=0;
    $tmp2 = 0;
    for ($i=0; $i < count($points); $i++) {
      $path[$i] = $Dij->shortestPaths(0, pointToGraph($points[$i]));
    }
    for ($i=0; $i < count($points); $i++) {
      for ($j=$i+1; $j < count($points); $j++) {
        $path[$i+$tmp+count($points)] = $Dij->shortestPaths(pointToGraph($points[$i]), pointToGraph($points[$j]));
        $tmp++;
      }
      $tmp--;
    }
    return $path;
  }

  function createGraph(array $grid) : array{
    global $N;
    $tmp = 0;
    $tmp2 = 0;
    for ($i=0; $i < $N; $i++) {
      for ($j=0; $j < $N; $j++) {
        $graph[$tmp][$tmp2+$j+1] = $graph[$tmp2+$j+1][$tmp] = $grid[$i][$j][0];
        $graph[$tmp][$tmp2+$j + $N] = $graph[$tmp2+$j + $N][$tmp] = $grid[$i][$j][1];
        $tmp++;
      }
      $tmp2 += $N;
    }

    for ($i=1; $i < $N; $i++) {
      unset($graph[$i*$N-1][$i*$N]);
      unset($graph[$N*$N-$N-1+$i][$N*$N-1+$i]);
      unset($graph[$i*$N][$i*$N-1]);
      unset($graph[$N*$N-1+$i]);
    }
    unset($graph[$N*$N-1][$N*$N]);
    unset($graph[$N*$N-1][$N*$N+$N-1]);
    unset($graph[$N*$N+$N-1]);

    return $graph;
  }

  function countWeight(array $graph, $paths) : array {
    $weights = [];
    foreach ($paths as $k => $value) {
      $tmpValue = $value;
      $tmpSum = 0;
      $first = current($tmpValue);

      while (true) {
        $currenKey = current($tmpValue);
        next($tmpValue);
        if (key($tmpValue) !== null) {
          $nextKey = current($tmpValue);
          $tmpSum += $graph[$currenKey][$nextKey];
        }
        else {
          break;
        }
      }
      $last = end($tmpValue);
      $weights["$first to $last"] = $tmpSum;
    }
    return $weights;
  }

  function DijOrderPath(array $FWgraph) : array {
    $graphOrder = [];
    $source = 0;
    array_push($graphOrder, $source);

    $next = $source;
    while (1) {
      $sorted = $FWgraph[$next];
      asort($sorted);

      unset($FWgraph[$next]);
      if(empty($FWgraph)){
        break;
      }

      foreach ($FWgraph as $key => $value) {
        unset($FWgraph[$key][$next]);
      }

      $next = array_keys($sorted)[0];
      $weight = array_values($sorted)[0];

      $graphOrder[$next] = $weight;
    }

    return $graphOrder;
  }

  function DijToGraph(array $points, array $weights) : array {
    $n = count($points) + 1; //+1 for $source
    $source = 0;

    $edgeOrder = [];
    $edgeOrder[0] = $source;

    $edge = 1;
    foreach ($points as $key => $value) {
      $edgeOrder[$edge++] = pointToGraph($value);
    }

    $edge = 0;
    for ($i=0; $i < $n; $i++) {
      for ($j=0; $j < $n; $j++) {
        if($i == $j){
          continue;
        }
        else {
          $FWgraph[$edgeOrder[$i]][$edgeOrder[$j]] = (isset($weights["$edgeOrder[$i] to $edgeOrder[$j]"]) ? $weights["$edgeOrder[$i] to $edgeOrder[$j]"] : (isset($weights["$edgeOrder[$j] to $edgeOrder[$i]"]) ? $weights["$edgeOrder[$j] to $edgeOrder[$i]"] : 0)); //assign value from $weights (in both diraction), no path than 0
        }
      }
    }

    $pathOrder = DijOrderPath($FWgraph);

    return $pathOrder;
  }

  function DijToPath(array $graphOrder, array $path) : array {
    $finalPaths = []; //return
    $graphPoints = array_keys($graphOrder);
    $from = array_shift($graphPoints);

    while(!empty($graphPoints)){
      $to = array_shift($graphPoints);

      foreach ($path as $key => $value) {
        $v = $value;
        $vStart = current($v);
        $vEnd = end($v);

        if($vStart == $from && $vEnd == $to){
          array_shift($v);
          $finalPaths = array_merge($finalPaths, $v);
        }
        elseif($vStart == $to && $vEnd == $from){
          array_pop($v);
          $finalPaths = array_merge($finalPaths, array_reverse($v));
        }
      }
      $from = $to;
    }

    return $finalPaths;
  }

  class Dijkstra {
  	/* The graph, where $graph[node1][node2]=cost */
  	protected $graph;
  	/* Distances from the source node to each other node */
  	protected $distance;
  	/* The previous node(s) in the path to the current node */
  	protected $previous;
  	/* Nodes which have yet to be processed */
  	protected $queue;

  	public function __construct($graph) {
  		$this->graph = $graph;
  	}
  	/*
  	 * Process the next (i.e. closest) entry in the queue
  	 * $exclude A list of nodes to exclude - for calculating next-shortest paths.
  	*/
  	protected function processNextNodeInQueue(array $exclude) {
  		// $closest closest vertex (with min weight from current point)
  		$closest = array_search(min($this->queue), $this->queue);
  		if (!empty($this->graph[$closest]) && !in_array($closest, $exclude)) { //jeśli wybrany wierzchołek ma trasy i nie byl jeszcze liczony
  			foreach ($this->graph[$closest] as $neighbor => $cost) {
  				if ($this->distance[$closest] + $cost < $this->distance[$neighbor]) {
  					// A shorter path was found
  					$this->distance[$neighbor] = $this->distance[$closest] + $cost;
  					$this->previous[$neighbor] = array($closest);
  					$this->queue[$neighbor]    = $this->distance[$neighbor];
  				}
  			}
  		}
  		unset($this->queue[$closest]);
  	}
  	/**
  	 * Extract all the paths from $source to $target as arrays of nodes.
  	 *
  	 */
  	protected function extractPaths($target) {
  		$paths = array(array($target));

  		while (current($paths)) {
  			$key  = key($paths);
  			$path = current($paths);
  			next($paths);
  			if (!empty($this->previous[$path[0]])) {
  				foreach ($this->previous[$path[0]] as $previous) {
  					$copy = $path;
  					array_unshift($copy, $previous);
  					$paths[] = $copy;
  				}
  				unset($paths[$key]);
  			}
  		}

  		return array_values($paths)[0];
  	}
  	/**
  	 * Calculate the shortest path through a a graph, from $source to $target.
  	 *
  	 * @param string   $source  The starting node
  	 * @param string   $target  The ending node
  	 * @param string[] $exclude A list of nodes to exclude - for calculating next-shortest paths.
  	 *
  	 * @return string[][] Zero or more shortest paths, each represented by a list of nodes
  	 */
  	public function shortestPaths($source, $target, array $exclude = array()) {
  		// The shortest distance to all nodes starts with infinity...
  		$this->distance = array_fill_keys(array_keys($this->graph), INF);
  		// ...except the start node
  		$this->distance[$source] = 0;
  		// The previously visited nodes
  		$this->previous = array_fill_keys(array_keys($this->graph), array());
  		// Process all nodes in order
  		$this->queue = array($source => 0);
  		while (!empty($this->queue)) {
  			$this->processNextNodeInQueue($exclude);
  		}
  		if ($source === $target) {
  			// A null path
  			return array(array($source));
  		} elseif (empty($this->previous[$target])) {
  			// No path between $source and $target
  			return array();
  		} else {
  			// One or more paths were found between $source and $target
  			return $this->extractPaths($target);
  		}
  	}
  }

  //notatki: updatePheromone() sprawdzic dla $this->pheromone[][][$t]
  // class AntColonyOptimalization {
  //   //input
  //   private $graph; // odleglosc miedzy punktami, [i][j] = distance
  //   private $targetPoints; // punkty dowozu
  //   private $antPopulationSize; // liczba mrowek
  //   private $alpha; //wpływ pozostawionego feromonu
  //   private $beta; //wpływ odleglosci miedzy punktami na wyliczone prawdopodobienstwo
  //   private $evapoRate; //współcznnik odparowania
  //   private $allTargetNeighbours = []; //punkty ktore sa sasiadami dla wszystkich target ($targetPoints) [target] = [pt, pt, pt ...]
  //
  //   //zmienne
  //   private $iter; //aktualna iteracja
  //   private $numberOfIter = 20;
  //   private $q3 = 1; //wspolczynnik dawki zostawionego feromonu
  //   private $p0 = 0.5; //startowy poziom feromonow
  //   private $neigh = 5; //maksymalnie jaki daleko sasiad w $allTargetNeighbours
  //
  //   private $best = PHP_INT_MAX;
  //   private $bestAnt = 0;
  //   private $bestIter = 0;
  //
  //   //dla kazdej mrowki osobno
  //   private $pheromone = []; // poziom feromonu na drodze miedzy punktami, [i][j] = value
  //   private $antsVisitedPoints = []; //(wszystkie) odwiedzone punkty przez mrowke, [k][iteracja] = [values ...]
  //
  //   private $distanceMatrix = []; //tablica dlugosci (czasu) zanlezionej trasy [k][iter] = sum
  //
  //   //nadpisywane co mrowke
  //   private $antNextPoints = []; //punkty ktorych mrowka jeszcze nie odwiedzila, a do ktorych prowadzi droga z obecnego punktu, [j1, j2 ...]
  //   private $possibilityWithNeighbours = []; // prawdopodobienstwo wybrania drogi przez mrowke do swoich sasiadow, [j] = value
  //   // private $antDistanceTraveled = []; //całkowita długośc trasy przebyta przez mrowkę
  //   private $antPointsToEnd = []; //punkty ktore mrowka musi jeszcze odwiedzic
  //   private $antTabuPoints = []; // punkty do ktorych mrowka nie moze wrocic [pt, pt, pt, pt, pt ...]
  //   // private $oscilationPoints = []; //punkty ktore gdy zostana wylosowane sa w poblizu targetow (nadpisywane co znaleziony punkt)
  //   private $antNumber; //numer przypisany aktualnej mrowce
  //
  //   // private $allowedPoints = []; //punkty w obrebie ktorych mrowka moze sie poruszac, zerowane co wybranie kolejnego punktu
  //   // private $isInNeighbour = false; //czy jestesmy w danej chwili w sasiedztwie
  //   private $countloop = 0; //numer przypisany aktualnej mrowce
  //
  //   //po iteracji kasuj
  //   private $bufPheromone = []; //wartosc feromonu znalezionego przez wszystkie mrowki w danej iteracji $bufPheromone [i][j][k] = value, k - mrowka
  //
  //   // private function getNeighbours($pt) : array{
  //   //   return array_keys($this->graph[$pt]);
  //   // }
  //
  //   // private function calTargetNeighbours(){
  //   //   $this->allTargetNeighbours = array();
  //   //   $j = $this->neigh; //jak doleko od punktu
  //   //
  //   //   foreach ($this->targetPoints as $trgt) {
  //   //     $this->allTargetNeighbours[$trgt] = [];
  //   //
  //   //     $neighbouars[0] = array($trgt);
  //   //     for ($i=1; $i <= $j; $i++) {
  //   //       $neighbouars[$i] = array();
  //   //       foreach ($neighbouars[$i-1] as $pt) {
  //   //         $neighbouars[$i] = array_merge($neighbouars[$i], $this->getNeighbours($pt));
  //   //       }
  //   //       $this->allTargetNeighbours[$trgt] = array_merge($this->allTargetNeighbours[$trgt], $neighbouars[$i]);
  //   //       $this->allTargetNeighbours[$trgt] = array_unique($this->allTargetNeighbours[$trgt], SORT_NUMERIC);
  //   //     }
  //   //   }
  //   // }
  //
  //   public function __construct($graph, $targetPoints, $antPopulationSize, $alpha, $beta, $evapoRate){
  //     $this->graph = $graph;
  //     $this->targetPoints = $targetPoints;
  //     $this->antPopulationSize = $antPopulationSize;
  //     $this->alpha = $alpha;
  //     $this->beta = $beta;
  //     $this->evapoRate = $evapoRate;
  //     // $this->calTargetNeighbours();
  //   }
  //
  //   private function calcPossibility($source, $dest) {
  //     $t = $this->iter; // aktualna iteracja
  //     $sum = 0; // zsumowany (dla wszsytkich sasiadow) wspolczynnik
  //     foreach ($this->antNextPoints as $v) {
  //       $sum += ($this->pheromone[$source][$v]**$this->alpha)*((1/$this->graph[$source][$v])**$this->beta);
  //     }
  //     $this->possibilityWithNeighbours[$dest] = ($this->pheromone[$source][$dest]**$this->alpha)*((1/$this->graph[$source][$dest])**$this->beta)/$sum;
  //   }
  //
  //   private function chooseNextPoint() : int {
  //     $randomNumber = random_int(0, PHP_INT_MAX-1)/PHP_INT_MAX;
  //     $bufPossibility = [];
  //     $offset = 0;
  //     foreach ($this->possibilityWithNeighbours as $key => $value) {
  //       $offset += $value;
  //       $bufPossibility[$key] = $offset;
  //     }
  //     foreach ($bufPossibility as $key => $value) {
  //       if($randomNumber < $value){
  //         return $key;
  //       }
  //     }
  //   }
  //
  //   //aktualizuj poziom feromonu po znalezieniu przez mrowke calej trasy
  //   private function calcBuffPheromone() {
  //     $t = $this->iter; //aktualna iteracja
  //     $allFoundedPoints = $this->antsVisitedPoints[$this->antNumber][$t]; //punkty przez ktore przeszla mrowka
  //     $i = array_shift($allFoundPoints);
  //
  //     while(!empty($allFoundPoints)){ // oblicz dawke feromonow pozostawioną przez mrowke
  //       $j = array_shift($allFoundPoints);
  //       $this->bufPheromone[$i][$j][$this->antNumber] = ($this->q3)/($this->graph[$i][$j]);
  //       $i = $j;
  //     }
  //   }
  //
  //   private function updatePheromone() {
  //     for ($k=0; $k < $this->antPopulationSize; $k++) {
  //       foreach ($this->pheromone as $i => $value) { //zaktualizuj poziom feromonow
  //         foreach ($value as $j => $weight) {
  //           $this->pheromone[$i][$j] = (1 - $this->evapoRate)*$this->pheromone[$i][$j] + (isset($this->bufPheromone[$i][$j][$k]) ? $this->bufPheromone[$i][$j][$k] : 0);
  //         }
  //       }
  //     }
  //   }
  //
  //   //checked
  //   private function initialPheromone() {
  //     foreach ($this->graph as $i => $value) { //zaktualizuj poziom feromonow
  //       foreach ($value as $j => $weight) {
  //         $this->pheromone[$i][$j] = $this->p0;
  //       }
  //     }
  //   }
  //
  //   private function stoppingCondition($pt){
  //     if(in_array($pt, $this->antPointsToEnd)) { //jesli punkt odwiedzony jest w tablicy targetow
  //
  //       unset($this->antPointsToEnd[array_search($pt, $this->antPointsToEnd)]); // usun ten punkt z listy punktow do odwiedzenia
  //       //$this->antTabuPoints = array();
  //       array_pop($this->antTabuPoints);
  //
  //       // if($this->isNeighbour($pt)){
  //       //   $this->calcAllowedPoints();
  //       // }
  //       if(empty($this->antPointsToEnd)){ //byl to ostatni punkt
  //         return false;
  //       }
  //       else {
  //         // array_push($this->antTabuPoints, $pt);
  //       }
  //     }
  //     return true;
  //   }
  //
  //   private function calcPossibleNextPoints($pt){
  //     foreach ($this->graph[$pt] as $j => $weight) {
  //       // if(!in_array($j, $this->antTabuPoints) && (empty($this->allowedPoints) || in_array($j, $this->allowedPoints))){
  //       if(!in_array($j, $this->antTabuPoints)){
  //         array_push($this->antNextPoints, $j);
  //       }
  //     }
  //   }
  //
  //   // private function calcAllowedPoints() {
  //   //   $this->allowedPoints = array();
  //   //   foreach ($this->antPointsToEnd as $target) {
  //   //     $this->allowedPoints = array_merge($this->allowedPoints, $this->allTargetNeighbours[$target]);
  //   //   }
  //   // }
  //
  //   //moznaby zoptymalizowac zwracajac $target i tylko dla nich obliczac $allowedPoints
  //   // private function isNeighbour($pt) {
  //   //   foreach ($this->allTargetNeighbours as $target => $neighbor) {
  //   //     if(in_array($pt, $neighbor)) {
  //   //       $this->isInNeighbour = true;
  //   //       return true;
  //   //     }
  //   //   }
  //   //   $this->isInNeighbour = false; //?
  //   //   return false;
  //   // }
  //
  //   private function InitiationForAnt(){ //resetuje $allowedPoints $antTabuPoints, ustawia $this->antPointsToEnd, inicjuje $this->antsVisitedPoints[$this->antNumber][$this->iter]
  //     // $this->allowedPoints = array();
  //     $this->antTabuPoints = array();
  //     $this->antPointsToEnd = array_merge(array(), $this->targetPoints);
  //     $this->antsVisitedPoints[$this->antNumber][$this->iter] = [];
  //     $this->distanceMatrix[$this->antNumber][$this->iter] = 0;
  //   }
  //
  //   private function InitiationForPoint(){
  //     $this->antNextPoints = array();
  //     $this->possibilityWithNeighbours = array();
  //     // $this->isInNeighbour = false;
  //   }
  //
  //
  //   public function runAlgorithm() : array{
  //     $this->initialPheromone();
  //
  //     for ($it=0; $it < $this->numberOfIter; $it++) {
  //       $this->iter = $it;
  //       $this->bufPheromone = array(); //resetuje poziom feromonow dla bufforowej tablicy
  //
  //       for ($ant=0; $ant < $this->antPopulationSize; $ant++) { //uruchamiam populacje mrowek dla danej iteracji
  //         $this->antNumber = $ant;
  //
  //         $this->InitiationForAnt(); //inicjacja
  //
  //         $currentPoint = 0; //ustawienie punktu starowego mrowek na [0,0]
  //         array_push($this->antTabuPoints, $currentPoint);
  //         array_push($this->antsVisitedPoints[$this->antNumber][$this->iter], $currentPoint);
  //
  //         while($this->stoppingCondition($currentPoint)){
  //           // echo '<pre>'; print_r($this->antTabuPoints); echo '</pre>';
  //           $this->InitiationForPoint();
  //
  //           // if(!$this->isInNeighbour){ // jesli nie jestesmy w sasiedztwie
  //           //   if($this->isNeighbour($currentPoint)){ //sprawdz czy punkt jest w sasiedztwie
  //           //     $this->calcAllowedPoints();
  //           //   }
  //           // }
  //
  //           $this->calcPossibleNextPoints($currentPoint);
  //
  //           $wasLoop=false;
  //           if(empty($this->antNextPoints)){ //jesli wystapila petla
  //             $this->antNumber = $ant--; //powtorz mrowke
  //             $this->countloop++;
  //             $wasLoop = true;
  //             break 1;
  //           }
  //           else{ //jesli nie ma petli
  //             foreach ($this->antNextPoints as $possiblePoint) {
  //               $this->calcPossibility($currentPoint, $possiblePoint);
  //             }
  //             $nextPoint = $this->chooseNextPoint();
  //             $this->distanceMatrix[$this->antNumber][$this->iter] += $this->graph[$currentPoint][$nextPoint];
  //             $currentPoint = $nextPoint;
  //             array_pop($this->antTabuPoints);
  //             array_push($this->antsVisitedPoints[$this->antNumber][$this->iter], $currentPoint);
  //             array_push($this->antTabuPoints, $currentPoint);
  //           }
  //         }
  //         if(!$wasLoop){
  //           $this->calcBuffPheromone();
  //         }
  //       }
  //       $this->updatePheromone();
  //
  //       for ($a=0; $a < $this->antPopulationSize; $a++) {
  //         if($this->distanceMatrix[$a][$this->iter] < $this->best){
  //           $this->best = $this->distanceMatrix[$a][$this->iter];
  //           $this->bestAnt = $a;
  //           $this->bestIter = $this->iter;
  //         }
  //       }
  //
  //     }
  //     echo 'loops: ' . $this->countloop;
  //
  //     return $this->antsVisitedPoints[$this->bestAnt][$this->bestIter];
  //   }
  //
  // }

  function pathWeight(array $graph, array $path) : int{
    $weight = 0;
    $i = array_shift($path);
    while(!empty($path)) {
      $j = array_shift($path);
      $weight += $graph[$i][$j];
      $i = $j;
    }
    return $weight;
  }

  $graph = createGraph($grid);
  ksort($graph);
  $points = pointGenerator($n);
  $pointsInGraph = [];
  foreach ($points as $key => $value) {
    $pointsInGraph[$key] = pointToGraph($value);
  }

  $Dij = new Dijkstra($graph);
  $path = allPathfind($Dij, $graph, $points);
  $weights = countWeight($graph, $path);
  $graphOrder = DijToGraph($points, $weights);
  $finalPaths = DijToPath($graphOrder, $path);
  $totalWeights = array_sum(array_values($graphOrder));

  $GeneticAlg = new AntColonyOptimalization($graph, $pointsInGraph, 20, 1, 4, 0.5);
  $geneticPath = $GeneticAlg->runAlgorithm();

  // echo '<pre>'; print_r($path); echo '</pre>';
  // echo '<pre>'; print_r($graph); echo '</pre>';
  echo '<br />Waga Dijikstra:' . $totalWeights . '<br />';
  echo '<pre>'; print_r($geneticPath); echo '</pre>  Waga ACO: ' . pathWeight($graph, $geneticPath);
  ?>

  <table id=gridtable></table>

</body>
<script src="jquery-3.3.1.min.js"></script>
<script type='text/javascript'>
  function generate() { //func for button grid generation
    jQuery(function($) {
      $.ajax( {
        url : "gridgenerator.php",
        type : "GET",
        success : function(data) {
          location.reload();
        }
      });
    });
  }

  var gridWeights = <?php echo(json_encode($grid)); ?>; //get grid from php
  var points = <?php echo(json_encode($points)); ?>; //get points from php
  var path = <?php echo(json_encode($path)); ?>; //get path from php

  var table = document.getElementById("gridtable");

  var row = [];
  var cell = [];
  var N = <?php echo $N; ?>; //get N from php

  function isEven(n) {
    return n == parseFloat(n)? !(n%2) : void 0;
  }
  function countNumber(n) {
    return 2*n - 1;
  }

  var amount = countNumber(N);

  for (let i = 0; i < amount; i++) {
    row[i] = {};
    row[i] = table.insertRow(i);
    cell[i] = [];
    for (var j = 0; j < amount; j++) {
      cell[i][j] = row[i].insertCell(j);
      cell[i][j].classList.add('gridcell');
    }
  }

  console.log(points);

  let tmpx = 0;
  let tmpright = 0;
  let tmpbottom = 0;
  for (let i = 0; i < amount; i++) {
    let tmpy = 0;
    for(let j = 0; j < amount; j++){
      if(isEven(i) && isEven(j)){
        cell[i][j].innerHTML =  tmpx + "|" + tmpy;
        cell[i][j].classList.add('gridcoor');
        tmpy++;
      }
      else if (isEven(i) && !isEven(j)) { //right
        cell[i][j].innerHTML = gridWeights[tmpbottom][tmpright][0];
        cell[i][j].classList.add('gridpath');

        tmpright++;
        if(tmpright == N-1){
          tmpright = 0;
        }
      }
      else if(!isEven(i) && isEven(j)) { //bottom
        cell[i][j].innerHTML = gridWeights[tmpbottom][tmpright][1];
        cell[i][j].classList.add('gridpath');
        tmpright++;
        if(tmpright == N){
          tmpright = 0;
          tmpbottom++;
        }
      }
      else{
        cell[i][j].classList.add('noborder');
      }
    }
    tmpx+=0.5;
  }
  for (var i = 0; i < points.length; i++) {
    let pointx = countNumber(points[i][0])+1;
    let pointy = countNumber(points[i][1])+1;
    cell[pointx][pointy].classList.add('gridpoint');
  }
  cell[0][0].classList.add('startpoint');

</script>
</html>
