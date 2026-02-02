let cardOne, cardTwo;
let disableClick = false; // Prevent clicks during animations
let matchedCount = 0;

// Function to shuffle the cards
function shuffleCards() {
    const wrapper = document.querySelector('.wrapper');
    const cards = Array.from(wrapper.children);//將.wrapper中的子元素(卡片)轉換為一個陣列
    cards.sort(() => Math.random() - 0.5); // 隨機排序陣列中的元素
    cards.forEach(card => wrapper.appendChild(card)); // 將隨機打亂後的陣列重新添加回.wrapper中
}
function openalert(){
    document.getElementById('customAlert').classList.add('active');
}
// Function to match cards
function matchCards(img1, img2) {
    if (img1 === img2) {//若兩張卡片圖片名相同則配對成功，將兩張圖片class加上matched
        cardOne.classList.add("matched");
        cardTwo.classList.add("matched");
        matchedCount += 2;
        resetCards();//空出兩張卡片選擇空間讓玩家再次選擇卡片
        const totalCards = document.querySelectorAll('.card').length;
        if (matchedCount === totalCards) {//若匹配卡片數量===詮不卡片數量，則宣布成功
            setTimeout(() => {
                openalert();
            }, 500); // Delay to ensure animations complete
        }
        return console.log("matched");
    }
    //若是兩張卡片名字不同代表不匹配，將兩張卡片class加上shake進行動畫
    setTimeout(() => {
        cardOne.classList.add("shake");
        cardTwo.classList.add("shake");
    }, 400);
    //將動畫移除以及去除fliped標籤，回到原本尚未翻開狀態
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
    if (disableClick) return; //防止玩家在動畫過程中點擊

    let clickedCard = e.currentTarget;//獲取當前被點擊的卡片元素並存儲在變數 clickedCard 中
    if (clickedCard === cardOne || clickedCard.classList.contains("fliped")) {
        return; //防止重複點擊相同card或是點擊以翻開card防止重複點擊相同card或是點擊以翻開card
    }

    clickedCard.classList.add("fliped");//被點擊到的class加上fliped
    clickedCard.querySelector(".frontview").style.display = "none";//將frontview也就是目前看到的圖片display轉為none隱藏
    clickedCard.querySelector(".backview").style.display = "flex";//將backview也就是需要配對的卡片display轉為flex使玩家看見

    if (!cardOne) { //如果 cardOne 尚未被設置，表示這是玩家選擇的第一張卡片，並讓玩家選擇第二張卡片
        cardOne = clickedCard; 
        return;
    }

    cardTwo = clickedCard; // 第二張卡片選擇
    disableClick = true; // Prevent further clicks during animation

    // Compare the images
    let cardOneImg = cardOne.querySelector(".backview img").src;//紀錄第一張卡片圖片名
    let cardTwoImg = cardTwo.querySelector(".backview img").src;//紀錄第二張卡片圖片名

    matchCards(cardOneImg, cardTwoImg);
}

// Add event listeners and shuffle cards on load
document.addEventListener("DOMContentLoaded", () => {
    shuffleCards(); // Shuffle the cards
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', flipcard); // Attach flip event
    });
});