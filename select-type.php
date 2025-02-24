<?php
session_start();

if(isset($_POST['userType'])) {
    $_SESSION['userType'] = $_POST['userType'];
    header("Location: signup.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join ServiceHive</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),url('images/login.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            text-align: center;
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo img {
            max-width: 300px;
            height: auto;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .options-container {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .option-card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 25px;
            width: 300px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .option-card.selected {
            border-color: rgb(220, 95, 17);
            background-color: #fff9f5;
        }

        .radio-input {
            position: absolute;
            top: 20px;
            right: 20px;
            accent-color: rgb(220, 95, 17);
            width: 20px;
            height: 20px;
        }

        .icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: rgb(220, 95, 17);
        }

        h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }

        p {
            color: #666;
            font-size: 14px;
        }

        .submit-btn {
            width: 200px;
            padding: 12px;
            background: rgb(220, 95, 17);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .submit-btn:hover {
            background: rgb(200, 85, 15);
        }

        .login-link {
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: rgb(220, 95, 17);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .options-container {
                flex-direction: column;
                align-items: center;
            }

            .option-card {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="images/logo2.png" alt="ServiceHive Logo">
        </div>
        <h1>Join as a Customer or Professional</h1>
        <form method="POST" action="">
            <div class="options-container">
                <div class="option-card" onclick="selectOption('client')">
                    <input type="radio" name="userType" value="client" class="radio-input" id="clientRadio">
                    <div class="icon">👤</div>
                    <h2>I'm a Customer</h2>
                    <p>Looking For professionals for your service needs</p>
                </div>

                <div class="option-card" onclick="selectOption('professional')">
                    <input type="radio" name="userType" value="professional" class="radio-input" id="professionalRadio">
                    <div class="icon">👨‍🔧</div>
                    <h2>I'm a Professional</h2>
                    <p>Looking to offer your services and grow your business</p>
                </div>
            </div>
            <button type="submit" class="submit-btn">Continue</button>
        </form>
        <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <script>
        function selectOption(type) {
            // Remove selected class from all cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            const selectedCard = document.querySelector(`input[value="${type}"]`).closest('.option-card');
            selectedCard.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${type}"]`).checked = true;
        }

        // Add click handler for radio buttons
        document.querySelectorAll('.radio-input').forEach(radio => {
            radio.addEventListener('click', (e) => {
                e.stopPropagation();
                selectOption(e.target.value);
            });
        });

        
    </script>
</body>
</html>