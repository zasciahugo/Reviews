<?php
session_start();
require_once 'config.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userToken = $_SESSION['user_token'] ?? bin2hex(random_bytes(16));
$_SESSION['user_token'] = $userToken;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $review_id = $_POST['review_id'] ?? 0;
    switch ($action) {
        case 'add':
            $user_name = $conn->real_escape_string($_POST['user_name'] ?? '');
            $review_text = $conn->real_escape_string($_POST['review_text'] ?? '');
            $rating = intval($_POST['rating'] ?? 0);

            if ($user_name && $review_text && $rating > 0 && $rating <= 5) {
                $stmt = $conn->prepare("INSERT INTO reviews (user_name, review_text, rating, user_token) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssis", $user_name, $review_text, $rating, $userToken);
                $stmt->execute();
                $stmt->close();
            }
            break;
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_token = ?");
            $stmt->bind_param("is", $review_id, $userToken);
            $stmt->execute();
            $stmt->close();
            break;
        case 'like':
            $stmt = $conn->prepare("INSERT INTO review_likes (review_id, user_token, liked) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE liked = NOT liked");
            $stmt->bind_param("is", $review_id, $userToken);
            $stmt->execute();
            $stmt->close();
            break;
    }
    header("Location: " . $_SERVER['PHP_SELF']); // Redirect to avoid resubmission
    exit();
}

// Query to fetch reviews along with like status for the current user
$reviewQuery = "SELECT r.*, (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id AND liked = 1) AS likes, (SELECT liked FROM review_likes WHERE review_id = r.id AND user_token = '$userToken') AS user_liked FROM reviews r ORDER BY r.created_at DESC";
$reviewsResult = $conn->query($reviewQuery);

$avgRatingQuery = "SELECT AVG(rating) as average_rating FROM reviews";
$avgResult = $conn->query($avgRatingQuery);
$averageRating = 0;
if ($avgResult && $avgResult->num_rows > 0) {
    $avgRow = $avgResult->fetch_assoc();
    $averageRating = round($avgRow['average_rating'], 1);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <title>Leave a Review</title>
    <style>
        .star {
            color: gold;
            font-size: 1.2em;
        }

        .like-button {
            display: flex;
            align-items: center;
            color: gray;
        }

        .like-button.liked {
            color: blue;
        }

        .like-button svg {
            fill: currentColor;
        }
    </style>
</head>

<body class="font-['Inter'] bg-gray-100 flex items-center justify-center h-screen text-sm">
    <div>
        <div class="bg-white p-8 rounded-lg border borer-gray-300 shadow-sm w-80 md:w-96 mx-auto">
            <h1 class="text-center text-2xl font-semibold">Leave a Review</h1>
            <div class="text-center text-gray-600">
                Overall Rating: <?= $averageRating ?> out of 5
                <span class="star"><?= str_repeat("★", floor($averageRating)) . str_repeat("☆", 5 - floor($averageRating)) ?></span>
            </div>
            <form id="reviewForm" method="post" class="space-y-2 mt-6">
                <input type="hidden" name="action" value="add">
                <div>
                    <input type="text" name="user_name" placeholder="First & Last Name" class="w-full mt-0.5 bg-gray-100 border border-gray-200 px-3 py-2.5 text-sm rounded-lg" required>
                </div>
                <div>
                    <textarea name="review_text" placeholder="Review" class="w-full mt-1 bg-gray-100 border border-gray-200 px-3 py-2.5 text-sm rounded-lg" required></textarea>
                </div>
                <div>
                    <input type="number" name="rating" placeholder="Rating (1-5)" min="1" max="5" class="w-full mb-1 bg-gray-100 border border-gray-200 px-3 py-2.5 text-sm rounded-lg" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 border border-blue-500 hover:border-blue-600 text-white px-3 py-2.5 rounded-lg block text-center">Submit Review</button>
            </form>
            <div id="reviewsContainer" class="mt-5">
                <h2 class="text-base font-semibold">Recent Reviews</h2>
                <?php if ($reviewsResult && $reviewsResult->num_rows > 0) : ?>
                    <?php while ($review = $reviewsResult->fetch_assoc()) : ?>
                        <div class="bg-gray-50 border border-gray-200 p-3 mt-2 rounded-lg">
                            <div class="flex justify-between items-center">
                                <p class="font-semibold"><?= htmlspecialchars($review['user_name']) ?></p>
                                <?php
                                $stars = str_repeat("★", $review['rating']) . str_repeat("☆", 5 - $review['rating']);
                                echo "<span class='font-normal star'>$stars</span>";
                                ?>
                            </div>
                            <p class="my-1"><?= htmlspecialchars($review['review_text']) ?></p>
                            <div class="flex justify-between items-center">
                                <p class="text-xs text-gray-500">Posted on <?= $review['created_at'] ?></p>

                                <?php if ($review['user_token'] === $userToken) : ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 underline text-xs underline-offset-2">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="border-t my-2"></div>

                            <div class="flex justify-between">
                                <p class="text-xs font-medium"><?= $review['likes'] ?> Likes</p>

                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    <input type="hidden" name="action" value="like">
                                    <button type="submit" class="like-button <?= $review['user_liked'] ? 'liked' : '' ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4">
                                            <path d="<?= $review['user_liked'] ? 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' : 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' ?>" />
                                        </svg>
                                        <span class="text-xs font-medium ml-0.5">Like</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <p>No reviews yet. Be the first to write one!</p>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <p class="text-center text-gray-400 text-xs mt-2">Developed by <a href="https://www.zasciahugo.com" class="underline">Zascia Hugo</a></p>
            <p class="text-center text-gray-400 text-xs">Copyright © 2024 Zascia Hugo</p>
        </div>
    </div>
</body>

</html>