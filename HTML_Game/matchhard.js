let cardOne, cardTwo;
let disableClick = false; // Prevent clicks during animations
let matchedCount = 0;

// Function to shuffle the cards
function shuffleCards() {
    const wrapper = document.querySelector('.wrapper');
    const cards = Array.from(wrapper.children);
    cards.sort(() => Math.random() - 0.5); // Shuffle the array
    cards.forEach(card => wrapper.appendChild(card)); // Reorder the DOM
}
function openalert(){
    document.getElementById('customAlert').classList.add('active');
}
// Function to match cards
function matchCards(img1, img2) {
    if (img1 === img2) {
        cardOne.classList.add("matched");
        cardTwo.classList.add("matched");
        matchedCount += 2;
        resetCards();
        const totalCards = document.querySelectorAll('.card').length;
        if (matchedCount === totalCards) {
            setTimeout(() => {
                openalert();
            }, 500); // Delay to ensure animations complete
        }
        return console.log("matched");
    }

    setTimeout(() => {
        cardOne.classList.add("shake");
        cardTwo.classList.add("shake");
    }, 400);

    setTimeout(() => {
        cardOne.classList.remove("shake", "fliped");
        cardTwo.classList.remove("shake", "fliped");
        cardOne.querySelector(".frontview").style.display = "flex";
        cardOne.querySelector(".backview").style.display = "none";
        cardTwo.querySelector(".frontview").style.display = "flex";
        cardTwo.querySelector(".backview").style.display = "none";
        resetCards();
    }, 1000);
}

// Reset card references
function resetCards() {
    cardOne = null;
    cardTwo = null;
    disableClick = false; // Allow further clicks
}

// Flip card function
function flipcard(e) {
    if (disableClick) return; // Block clicks during animation

    let clickedCard = e.currentTarget;
    if (clickedCard === cardOne || clickedCard.classList.contains("fliped")) {
        return; // Prevent double-clicking the same card or clicking already flipped cards
    }

    clickedCard.classList.add("fliped");
    clickedCard.querySelector(".frontview").style.display = "none";
    clickedCard.querySelector(".backview").style.display = "flex";

    if (!cardOne) {
        cardOne = clickedCard; // First card selected
        return;
    }

    cardTwo = clickedCard; // Second card selected
    disableClick = true; // Prevent further clicks during animation

    // Compare the images
    let cardOneImg = cardOne.querySelector(".backview img").src;
    let cardTwoImg = cardTwo.querySelector(".backview img").src;

    matchCards(cardOneImg, cardTwoImg);
}

// Add event listeners and shuffle cards on load
document.addEventListener("DOMContentLoaded", () => {
    shuffleCards(); // Shuffle the cards
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', flipcard); // Attach flip event
    });
});