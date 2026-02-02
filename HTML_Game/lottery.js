// Code for user submission (number input)
const numbers = [];
document.getElementById("startButton").disabled = true;
document.addEventListener("DOMContentLoaded", () => {
    const numberInput = document.getElementById("numberInput");
    const submitButton = document.getElementById("submitButton");
    const numbersDisplay = document.getElementById("numbersDisplay");

    // Store entered numbers
    

    // Function to display numbers
    function displayNumbers() {
        numbersDisplay.innerHTML = ""; // Clear the display
        numbers.forEach((num) => {
            const numberBox = document.createElement("div");
            numberBox.classList.add("number-box");
            numberBox.textContent = num;
            numbersDisplay.appendChild(numberBox);
        });
    }
  
    // Function to handle input submission
    function submitNumber() {
        const value = parseInt(numberInput.value.trim(), 10); // Get and parse the input value

        // Validate input (only allow numbers between 1 and 49)
        if (value >= 1 && value <= 49 && !numbers.includes(value)) {
            numbers.push(value); // Add the number to the array
            displayNumbers(); // Update the display
            numberInput.value = ""; // Clear the input
            numberInput.focus(); // Focus back on the input
        } else if (numbers.includes(value)) {
            alert("This number is already entered. Please enter a different number.");
        } else {
            alert("Please enter a valid number between 1 and 49.");
        }

        // Prevent the array from growing beyond 6 numbers
        if (numbers.length >= 6) {
            alert("Now you finished choosing number. Please start lottery and wish you have fun");
            numberInput.disabled = true; // Disable the input
            submitButton.disabled = true; // Disable the button
            startButton.disabled = false;
        }
    }

    // Submit button click event
    submitButton.addEventListener("click", submitNumber);

    // Handle "Enter" key press
    numberInput.addEventListener("keypress", (event) => {
        if (event.key === "Enter") {
            event.preventDefault(); // Prevent default form submission
            submitNumber(); // Trigger the submission
        }
    });
});


// Array to hold ball elements
let aBall = [];

// Select existing ball divs
for (let i = 0; i < 6; i++) {
    let ball = document.getElementById(`ball${i + 1}`);
    aBall.push(ball);
}

// Function to generate an array of unique random numbers from 1 to 49
function generateUniqueNumbers() {
    const numbers = Array.from({ length: 49 }, (_, i) => i + 1); // Create array [1, 2, ..., 25]
    for (let i = numbers.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [numbers[i], numbers[j]] = [numbers[j], numbers[i]]; // Shuffle the array
    }
    return numbers.slice(0, 6); // Return first 6 numbers as unique lottery numbers
}

// Function to start the lottery draw
function startLottery() {
    // Reset balls to placeholder "00" before each draw
    for (let i = 0; i < 6; i++) {
        aBall[i].innerText = "00";
    }

    // Get a set of unique random numbers for the draw
    const lotteryNumbers = generateUniqueNumbers();

    // Function to update each ball in sequence with a delay
    function drawBall(index) {
        if (index >= aBall.length) {
            checkResults(lotteryNumbers); // Compare user numbers with lottery numbers
            return; // Stop if all balls are drawn
        }

        // Start random number generation for the current ball
        aBall[index].innerText = "00"; // Set initial text to "00"
        const targetNumber = lotteryNumbers[index]; // Get the unique number for this ball

        // Animate changing numbers before showing the final number
        let tempTimer = setInterval(function () {
            aBall[index].innerText = Math.ceil(Math.random() *49 ).toString().padStart(2, '0');
        }, 50); // Update every 100 milliseconds for effect

        // Stop the animation and set the final unique number after 2 seconds
        setTimeout(function () {
            clearInterval(tempTimer);
            aBall[index].innerText = targetNumber.toString().padStart(2, '0'); // Show final unique number
            drawBall(index + 1); // Move to the next ball in order
        }, 1000);
    }

    // Start the lottery draw from the first ball
    drawBall(0);
    
}
function alerta(lotteryNumbers, userNumbers, matchedNumbers) {
    const resultDiv = document.createElement("div");
    resultDiv.classList.add("result-div");
    document.body.appendChild(resultDiv);

    if (matchedNumbers.length > 1) {
        resultDiv.innerHTML = `
            <p>You matched ${matchedNumbers.length} number(s)!</p>
            <button id="winButton1">Home</button>
            <button id="winButton2">挑戰Easy</button>
        `;

        document.getElementById("winButton1").addEventListener("click", () => {
            window.location.href = "start.html"; // Replace with the actual prize page URL
        });

        document.getElementById("winButton2").addEventListener("click", () => {
            window.location.href = "easy.html"; // Replace with the detailed results page URL
        });
    } else {
        resultDiv.innerHTML = `
            <p>Not enough matches this time. Better luck next time!</p>
            <button id="tryAgainButton">Try Again</button>
        `;

        document.getElementById("tryAgainButton").addEventListener("click", () => {
            window.location.reload(); // Reload the page to start again
        });
    }
}
// Function to compare user submissions with the lottery outcome
function checkResults(lotteryNumbers) {
    const userNumbers = numbers; // Use the global `numbers` array
    const matchedNumbers = userNumbers.filter((num) => lotteryNumbers.includes(num)); // Find matching numbers
    console.log(userNumbers);
    console.log(lotteryNumbers);
    console.log(matchedNumbers);
    // Alert the result after 2.5 seconds
    setTimeout(() => alerta(lotteryNumbers,userNumbers,matchedNumbers), 800);
}
// Add an event listener to the "Start Lottery" button
document.getElementById("startButton").addEventListener("click", startLottery);