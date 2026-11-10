// 1. 初始化遊戲數值與位置（從本地存儲讀取）
let currentWealth = parseInt(localStorage.getItem('wealth')) || 0;
let currentRep = parseInt(localStorage.getItem('rep')) || 0;

// 讀取存檔的路徑與座標，如果沒紀錄就用預設值
let savedPath = localStorage.getItem('pathPoints');
let pathPoints = savedPath ? JSON.parse(savedPath) : ["200,500"]; 
let lastX = localStorage.getItem('charX') || "200px";
let lastY = localStorage.getItem('charY') || "500px";

// 2. 網頁加載時自動恢復進度
window.onload = function() {
    // 恢復數值
    document.getElementById('wealth').innerText = currentWealth;
    document.getElementById('rep').innerText = currentRep;
    
    // 恢復角色位置
    const char = document.getElementById('character');
    char.style.left = lastX;
    char.style.top = lastY;
    
    // 恢復畫出的路徑線
    const pathLine = document.getElementById('path-line');
    if (pathLine) {
        pathLine.setAttribute("d", "M " + pathPoints.join(" L "));
    }
};

const gameData = {
    'start': {
        title: "The Pier of Dreams",
        desc: "You arrived with nothing but a dream. To build an empire, you must explore the trades of Talat Noi.",
        img: "talat-noi-street.jpg",
        isGame: false
    },
    'solheng': {
        title: "Sol Heng Tai Mansion",
        desc: "The clan master shares a cup of 'Ancestral Coffee'. But first, you must prove your wisdom in his hard math challenge!",
        img: "Sol-Heng-Tai-Mansion.jpg",
        isGame: true,
        gameUrl: "2048.html" 
    },
    'bar32': {
        title: "32Bar",
        desc: "The secrets of chocolate are hidden behind the store owner's hobby. Complete the matching challenge to earn your reward.",
        img: "bar32.jpg",
        isGame: true,
        gameUrl: "match.html"
    },
    'vanich': {
        title: "Vanich House",
        desc: "A collection of curios! You need some luck to find the treasure that shopkeeper hide in the grocery store.",
        img: "vanich-house.jpg",
        isGame: true,
        gameUrl: "lotteryeasy.html"
    },
    'naam': {
        title: "NAAM 1608",
        desc: "The banquet is starting! You need to prepare the knowledge of Thailand.",
        img: "1608.jpg",
        isGame: true,
        gameUrl: "wordle.html"
    },
    'museum': {
    title: "Talat Noi Museum: The Legend's End",
    desc: "To become a legend of Talat Noi, you must have amassed a fortune of 1,000 wealth and a reputation of 250. Do you have what it takes?",
    img: "museum.jpg",
    isGame: false // We change this because it's now a stat-check, not a mini-game
    }
};

// 3. 處理點擊與移動
function handleInteraction(key, element) {
    const x = element.style.left;
    const y = element.style.top;

    const char = document.getElementById('character');
    char.style.left = x;
    char.style.top = y;

    // 儲存當前位置到存檔
    localStorage.setItem('charX', x);
    localStorage.setItem('charY', y);
    
    updatePath(parseInt(x), parseInt(y));

    setTimeout(() => {
        showStory(key);
    }, 1200);
}

// 4. 更新並儲存路徑
function updatePath(x, y) {
    pathPoints.push(`${x},${y}`);
    document.getElementById('path-line').setAttribute("d", "M " + pathPoints.join(" L "));
    
    // 將路徑陣列轉為字串儲存
    localStorage.setItem('pathPoints', JSON.stringify(pathPoints));
}



function showStory(key) {
    const data = gameData[key];
    const actionBtn = document.querySelector('.btn');
    const rewardText = document.getElementById('reward-text');
    const modalDesc = document.getElementById('modal-desc');
    
    document.getElementById('modal-title').innerText = data.title;
    modalDesc.innerText = data.desc;
    document.getElementById('modal-img').src = data.img;
    rewardText.innerHTML = ""; // Clear previous QR or text

    if (key === 'museum') {
        // 1. Check if player meets the requirements
        if (currentWealth >= 1000 && currentRep >= 250) {
        actionBtn.innerText = "Finish Journey";
        actionBtn.onclick = function() {
            // 1. 關閉原本的故事 Modal
            closeModal();
            
            // 2. 填入數據到全螢幕證書
            document.getElementById('cert-wealth').innerText = currentWealth;
            document.getElementById('cert-rep').innerText = currentRep;
            document.getElementById('cert-date').innerText = new Date().toLocaleDateString();
            
            // 3. 顯示全螢幕證書
            document.getElementById('certificate-overlay').style.display = 'flex';
        };
        } else {
            // 2. If requirements are NOT met
            const missingWealth = 1001 - currentWealth;
            const missingRep = 251 - currentRep;
            
            modalDesc.innerText = `The elders are not impressed yet. You need ${missingWealth > 0 ? missingWealth : 0} more Wealth and ${missingRep > 0 ? missingRep : 0} more Reputation to enter the Hall of Fame.`;
            
            actionBtn.innerText = "Keep Grinding";
            actionBtn.onclick = closeModal;
        }
    } 
    // Logic for other locations (Mini-games)
    else if (data.isGame) {
        actionBtn.innerText = "Start Challenge";
        actionBtn.onclick = function() {
            window.location.href = data.gameUrl;
        };
    } else {
        // Logic for 'start' or other non-game spots
        actionBtn.innerText = "Continue Your Business";
        actionBtn.onclick = function() {
            if (key === 'start' && !localStorage.getItem('started')) {
                updateStats(10, 5);
                localStorage.setItem('started', 'true');
            }
            closeModal();
        };
    }

    document.getElementById('story-modal').style.display = 'block';
    document.getElementById('overlay').style.display = 'block';
}
function updateStats(w, r) {
    currentWealth += w;
    currentRep += r;
    document.getElementById('wealth').innerText = currentWealth;
    document.getElementById('rep').innerText = currentRep;
    
    localStorage.setItem('wealth', currentWealth);
    localStorage.setItem('rep', currentRep);
}

function closeModal() {
    document.getElementById('story-modal').style.display = 'none';
    document.getElementById('overlay').style.display = 'none';
}
function resetGame() {
    if (confirm("Are you sure you want to start a new journey? All progress will be lost!")) {
        localStorage.clear(); // 清除所有存儲
        window.location.reload(); // 重新整理網頁
    }
}