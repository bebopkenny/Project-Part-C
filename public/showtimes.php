<?php

// Database connection
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get movie_id from URL
$movie_id = param('movie_id', null, 'GET');
$theatre_id = param('theatre_id', null, 'GET');
$date = param('date', null, 'GET');  // Optional date filter

// If no movie selected, redirect to movies page
// This is under the assumption the user picks a movie and then displays showtimes from my understanding
if (!$movie_id) {
    header('Location: movies.php');
    exit;
}

// Fetch movie details
$stmt = $pdo->prepare("SELECT * FROM movie WHERE MovieID = :movie_id");
$stmt->execute([':movie_id' => $movie_id]);
$movie = $stmt->fetch();

// If movie not found, redirect
if (!$movie) {
    header('Location: movies.php');
    exit;
}

// Fetch all theatres for filter dropdown
$theatres = $pdo->query("SELECT TheatreID, Name FROM theatre ORDER BY Name")->fetchAll();

// Build the showtimes query
// Joins: showtime -> movie, showtime -> auditorium -> theatre
$sql = "
    SELECT
        s.ShowtimeID,
        s.StartTime,
        s.Format,
        s.Language,
        s.BasePrice,
        a.Name AS AuditoriumName,
        t.TheatreID,
        t.Name AS TheatreName,
        t.City
    FROM showtime AS s
    JOIN auditorium AS a ON s.AuditoriumID = a.AuditoriumID
    JOIN theatre AS t ON a.TheatreID = t.TheatreID
    WHERE s.MovieID = :movie_id
";

//    This filters out past showtimes, right now for testing it showcases all start times
//    AND s.StartTime >= NOW()

// Theatre filter if theatre is selected for filter
if ($theatre_id){
  $sql .= " AND t.TheatreID = :theatre_id";
}
if ($date) {
  $sql .= " AND DATE(s.StartTime) = :date";
}
$sql .= " ORDER BY t.Name, s.StartTime";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':movie_id', $movie_id, PDO::PARAM_INT);
if ($theatre_id) {
    $stmt->bindParam(':theatre_id', $theatre_id, PDO::PARAM_INT);
}
if ($date) {
    $stmt->bindParam(':date', $date);
}
$stmt->execute();
$showtimes = $stmt->fetchAll();


// Data Processing, group showtimes with its date then by theatre
$grouped = [];
foreach ($showtimes as $show) {
    $showDate = date('Y-m-d', strtotime($show['StartTime']));
    $grouped[$showDate][$show['TheatreName']][] = $show;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Showtimes</title>
    <style>
        /* Temporary styling, could put it into styles.css however it may conflict with other styling from index, unsure */
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #fafafa; }
        .back-link { color: #007bff; text-decoration: none; }

        /* Movie header */
        .movie-header { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .movie-header h1 { margin: 0 0 10px 0; }
        .movie-meta { color: #666; }

        /* Filters */
        .filters { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filters label { font-weight: bold; }
        .filters select, .filters input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .filters button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }

        /* Date sections */
        .date-section { margin-bottom: 25px; }
        .date-header { background: #4da3ffff; color: white; padding: 10px 15px; margin: 0; border-radius: 5px 5px 0 0; }

        /* Theatre sections */
        .theatre-section { border: 1px solid #ddd; border-top: none; }
        .theatre-name { background: #333; color: white; padding: 10px 15px; margin: 0; font-size: 16px; }

        /* Showtime buttons */
        .showtime-list { padding: 15px; background: #fff; display: flex; flex-wrap: wrap; gap: 10px; }
        .showtime-btn { display: inline-block; padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; text-align: center; }
        .showtime-btn:hover { background: #218838; }
        .showtime-time { font-weight: bold; font-size: 16px; }
        .showtime-info { font-size: 12px; margin-top: 3px; }

        .no-results { padding: 40px; text-align: center; color: #666; background: #fff; border-radius: 5px; }
    </style>
</head>
<body>
    <a href="movies.php?id=<?php echo esc($movie_id); ?>" class="back-link"> ← Back to Movie Details</a>

    <!-- Movie Info Header -->
    <div class="movie-header">
        <h1><?php echo esc($movie['Name']); ?></h1>
        <div class="movie-meta">
            <?php echo esc($movie['Runtime']); ?> min |
            <?php echo strtoupper(esc($movie['MPAA'])); ?> |
            Released: <?php echo date('M j, Y', strtotime($movie['ReleaseDate'])); ?>
        </div>
    </div>

    <div class="filters">
      <form method="GET">
        <!-- Keep movie ID when filtering -->
        <input type="hidden" name="movie_id" value="<?php echo esc($movie_id); ?>">

        <label>Theatre:</label>
        <select name="theatre_id">
          <!-- Defaulted option to All Theatres -->
          <option value="">All Theatres</option>
          <!-- Loops through each theatre from database -->
          <?php foreach ($theatres as $t): ?>
            <!-- $t['TheatreID'] will be used for filtering certain theatres -->
            <!-- $t['Name'] to display the theatre name -->
            <option value="<?php echo $t['TheatreID']; ?>"
              <?php echo ($theatre_id == $t['TheatreID']) ? 'selected' : ''; ?>>
              <?php echo $t['Name']; ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Select a certain date to look for certain showtimes, defaults to current date -->
        <label>Date:</label>
        <input type="date" name="date" value="<?php echo $date; ?>">

        <button type="submit">Search</button>
      </form>
    </div>

    <!-- Showtimes by Date and Theatre -->
    <div class="showtimes">
        <?php if (empty($grouped)): ?>
            <div class="no-results">No upcoming showtimes found for this movie.</div>

        <?php else: ?>
            <?php foreach ($grouped as $showDate => $theatres): ?>
                <div class="date-section">
                    <!-- Date Header -->
                    <h2 class="date-header"><?php echo date('l, F j, Y', strtotime($showDate)); ?></h2>

                    <?php foreach ($theatres as $theatreName => $shows): ?>
                        <div class="theatre-section">
                            <h3 class="theatre-name"><?php echo esc($theatreName); ?></h3>

                            <div class="showtime-list">
                                <?php foreach ($shows as $show): ?>
                                    <a href="seats.php?showtime_id=<?php echo esc($show['ShowtimeID']); ?>" class="showtime-btn">
                                        <div class="showtime-time"><?php echo date('g:i A', strtotime($show['StartTime'])); ?></div>
                                        <div class="showtime-info">
                                            <?php echo strtoupper(esc($show['Format'])); ?> |
                                            $<?php echo number_format($show['BasePrice'], 2); ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


</body>
</html>
