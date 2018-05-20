<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Form Feedback</title>
</head>
<body>
  <?php
  $P = 5;
  $file = fopen("grid.txt", "w") or die("Unable to open file!");
  for($i = 0; $i <= $N; $i++){
    for($j = 0; $j <= $N; $j++){
      $grid[$i][$j] = rand(1, 50);
    }
    $tmpgrid = implode(" ", $grid[$i]);
    $tmpgrid .= "\r\n";
    fwrite($file, $tmpgrid);
  }
  fclose($file);
  ?>
</body>
</html>
