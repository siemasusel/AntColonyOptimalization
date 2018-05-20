<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Form Feedback</title>
  <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
  <?php
  $N = 10;
  $randmax = 9;
  $tmpgrid = '';
  for($i = 0; $i < $N-1; $i++){
    for($j = 0; $j < $N-1; $j++){
      $grid[$i][$j][0] = rand(1, $randmax);
      $grid[$i][$j][1] = rand(1, $randmax);
    }
    $grid[$i][$N-1][0] = 0;
    $grid[$i][$N-1][1] = rand(1, $randmax);

  }
  for ($i=0; $i < $N-1; $i++) {
    $grid[$N-1][$i][0] = rand(1, $randmax);
    $grid[$N-1][$i][1] = 0;
  }
  $grid[$N-1][$N-1][0] = 0;
  $grid[$N-1][$N-1][1] = 0;

  for($i = 0; $i < $N-1; $i++){
    for ($j=0; $j < $N-1; $j++) {
      $tmpgrid .= $grid[$i][$j][0];
      $tmpgrid .= " ";
    }
    $tmpgrid .= $grid[$i][$N-1][0];
    $tmpgrid .= "\r\n";
    for ($j=0; $j < $N - 1; $j++) {
      $tmpgrid .= $grid[$i][$j][1];
      $tmpgrid .= " ";
    }
    $tmpgrid .= $grid[$i][$N - 1][1];
    $tmpgrid .= "\r\n\r\n";
  }
  for ($j=0; $j < $N-1; $j++) {
    $tmpgrid .= $grid[$N-1][$j][0];
    $tmpgrid .= " ";
  }
  $tmpgrid .= $grid[$N-1][$N-1][0];
  $tmpgrid .= "\r\n";
  for ($j=0; $j < $N-1; $j++) {
    $tmpgrid .= $grid[$N-1][$j][1];
    $tmpgrid .= " ";
  }
  $tmpgrid .= $grid[$N-1][$N-1][1];

  // echo '<pre>'; print_r($grid); echo '</pre>';
  //echo $tmpgrid;
  file_put_contents("grid.txt", $tmpgrid);
  ?>
</body>
</html>
