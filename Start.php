<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>NBA Fantasy</title>
    <style>
        html,
        body {
            margin: 0;
            height: 100%;
            overflow: hidden;
        }

        body {
            background: #000 url("asset/start.jpg") center / cover no-repeat;
            cursor: pointer;
            user-select: none;
        }
        .game-name {
            position: fixed;
            left: 50%;
            bottom: 25%;
            transform: translateX(-50%);
            color: #fff;
            font-size: clamp(2.5rem, 8vw, 6rem);
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: none;
            white-space: nowrap;
            text-shadow: 0 0 10px #000, 0 0 25px rgba(255, 140, 0, 0.9);
        }

        .game-name span {
            display: inline-block;
            opacity: 0;
            animation: pop-in 0.4s ease-out forwards;
        }

        @keyframes pop-in {
            from { opacity: 0; transform: translateY(-30px) scale(1.5); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .press-start {
            position: fixed;
            left: 50%;
            bottom: 12%;
            transform: translateX(-50%);
            color: #fff;
            font-size: clamp(1.5rem, 4vw, 3rem);
            font-weight: bold;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            white-space: nowrap;
            text-shadow: 0 0 8px #000, 0 0 16px rgba(255, 140, 0, 0.8);
            animation: flash 1.2s ease-in-out infinite;
        }

        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
</head>

<body>
    <div class="game-name">NBA Fantasy</div>
    <div class="press-start">Press to Start</div>

    <script>
        // Split the game name into letters and reveal them one by one
        const gameName = document.querySelector(".game-name");
        const text = gameName.textContent;
        gameName.textContent = "";
        [...text].forEach((ch, i) => {
            const span = document.createElement("span");
            span.textContent = ch === " " ? " " : ch;
            span.style.animationDelay = (i * 0.15) + "s";
            gameName.appendChild(span);
        });

        function pressToStart() {
            window.location.href = "login.php";
        }
        document.addEventListener("click", pressToStart);
        document.addEventListener("keydown", pressToStart);
    </script>
</body>

</html>
