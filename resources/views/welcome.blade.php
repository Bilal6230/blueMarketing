<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlueMarketing</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.7/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            background-color: #0069D9; /* Updated background color code */
            color: #ffffff; /* White text color */
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        #logo {
            font-size: 72px; /* Increased font size */
            text-align: center;
            text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.8); /* Increased text shadow */
            font-weight: bold; /* Bold font weight */
            color: #3498db; /* Blue text color */
            -webkit-background-clip: text; /* Clip text to the background */
            background-clip: text;
            color: transparent; /* Make the text transparent */
            background-image: linear-gradient(white, white); /* White outline */
            animation: rotateLogo 5s infinite alternate, pulseLogo 2s infinite; /* Added 3D rotation and pulsating animations */
        }

        @keyframes rotateLogo {
            0% {
                transform: rotateY(0deg);
            }
            100% {
                transform: rotateY(360deg);
            }
        }

        @keyframes pulseLogo {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        #slogan {
            font-size: 24px;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 id="logo" class="animate__animated animate__fadeInDownBig">BlueMarketing</h1>
        <p id="slogan" class="animate__animated animate__fadeInUpBig">Innovate. Captivate. Succeed.</p>
    </div>

    <!-- Adding animate.css class to provide animation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.js"></script>
</body>
</html>
