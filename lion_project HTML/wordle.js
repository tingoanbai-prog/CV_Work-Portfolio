"use strice";
    let currentRow = 0;

    // 建立Wordle格子
    function initBoard() {
      let html = '<div id="board">';
      for (let i = 0; i < 30; i++) {
          html += `<div id="letter${i}" class="letter"></div>`;
      }
      html += '</div>';
  
      html += '<div id="keyboard">';
      for (let c of "QWERTYUIOP-ASDFGHJKL=ZXCVBNM") {
          if (c === '-') {
              html += `<button id="Backspace" class="key">&#x2190;</button><br>`; // 退格键
          } else if (c === '=') {
              html += `<button id="Enter" class="key">&#x219a;</button><br>`; // 回车键
          } else {
              html += `<button id="Key${c}" class="key">${c}</button>`;
          }
      }
      html += '</div>';
      html += `
        <div id="gameResult" class="dialog" style="display:none;">
        <p id="resultMessage"></p>
        </div>`;
      document.getElementById("context").innerHTML = html;
      
  }
  function restartGame() {
    currentRow = 0;
    cursor = 0;
    
    // 重新生成新單詞並輸出到 console
    secret = DICTIONARY[Math.floor(Math.random() * DICTIONARY.length)];
    console.log("新一局的正確答案是：", secret);
    
    for (let letter of aLetter) {
        letter.innerText = '';
        letter.className = 'letter'; // 重置格子的樣式
    }
    for (let key of aButton) {
        key.className = 'key'; // 重置按鍵的樣式
    }
    
    document.getElementById("gameResult").style.display = "none"; // 隱藏結果框
}
function showGameResult(message, success = false) {
  let resultContainer = document.getElementById("gameResult");
  
  // 清空之前的內容
  resultContainer.innerHTML = "";
  
  let msgP = document.createElement("p");
  msgP.id = "resultMessage";
  msgP.innerText = message;
  resultContainer.appendChild(msgP);
  
  if (success) {
      // --- 獎勵設定 ---
      const rewardWealth = 300; // NAAM 1608 獎勵較高，因為 Wordle 比較難
      const rewardRep = 100;

      // 1. 顯示獎勵文字
      let rewardP = document.createElement("p");
      rewardP.style.color = "#FFD700";
      rewardP.style.fontWeight = "bold";
      rewardP.innerText = `Reward: +$${rewardWealth} Wealth, +${rewardRep} Reputation`;
      resultContainer.appendChild(rewardP);

      // 2. 更新 localStorage 的分數
      updateNaamStats(rewardWealth, rewardRep);

      // 3. 建立「領取並回地圖」按鈕
      let btn1 = document.createElement("button");
      btn1.innerText = "Claim & Return to Map";
      btn1.onclick = () => { window.location.href = "index.html"; }; 
      btn1.classList.add("result-button1");
      resultContainer.appendChild(btn1);

  } else {
      // 失敗時的按鈕
      let restartBtn = document.createElement("button");
      restartBtn.innerText = "Try Again";
      restartBtn.onclick = restartGame;
      restartBtn.classList.add("restart-button");
      resultContainer.appendChild(restartBtn);
      
      let backBtn = document.createElement("button");
      backBtn.innerText = "Give up";
      backBtn.style.marginLeft = "10px";
      backBtn.onclick = () => { window.location.href = "index.html"; };
      resultContainer.appendChild(backBtn);
  }

  resultContainer.style.display = "block";
}

// 輔助函式：將分數存入瀏覽器紀錄
function updateNaamStats(w, r) {
    let currentW = parseInt(localStorage.getItem('wealth')) || 0;
    let currentR = parseInt(localStorage.getItem('rep')) || 0;

    localStorage.setItem('wealth', currentW + w);
    localStorage.setItem('rep', currentR + r);
}
initBoard();
let secret=DICTIONARY[Math.ceil(Math.random()*DICTIONARY.length)];
console.log(secret);
let cursor=0;
let aLetter=document.getElementsByClassName('letter');
let aButton=document.getElementsByClassName('key');
function keyProcess(code, key) {
  if (code == 'Backspace') {
    if (aLetter[cursor].innerText === '') {
      if (cursor % 5 > 0) cursor--;
    }
    aLetter[cursor].innerText = '';
    aLetter[cursor].classList.remove('input-letter');
  } 
  else if (code == 'Enter') {
    if (cursor % 5 === 4 && aLetter[cursor].innerText !== '') {
      let guess = '';
      let tempSecret = secret.split(''); // 用於避免重複檢查的暫存陣列

      for (let i = cursor - 4; i <= cursor; i++) {
        let ch = aLetter[i].innerText.toLowerCase();
        let keyElement = document.getElementById(`Key${ch.toUpperCase()}`);
        guess += ch;

        if (tempSecret[i % 5] === ch) {
          // 正確位置
          aLetter[i].classList.add('letterGreen');
          if (!keyElement.classList.contains('letterGreen')) {
            keyElement.classList.remove('letterYellow', 'letterGrey');
            keyElement.classList.add('letterGreen');
          }
          tempSecret[i % 5] = null; // 標記該位置已匹配
        } else if (tempSecret.includes(ch)) {
          // 字母存在但位置錯誤
          aLetter[i].classList.add('letterYellow');
          if (!keyElement.classList.contains('letterGreen') && !keyElement.classList.contains('letterYellow')) {
            keyElement.classList.add('letterYellow');
          }
          tempSecret[tempSecret.indexOf(ch)] = null; // 標記已匹配
        } else {
          // 字母不存在於 secret
          aLetter[i].classList.add('letterGrey');
          if (!keyElement.classList.contains('letterGreen') && !keyElement.classList.contains('letterYellow')) {
            keyElement.classList.add('letterGrey');
          }
        }
      }

      console.log(guess);
      if (guess === secret) {
        showGameResult("Congratulations", true); // 传递 true 表示猜对
      } else {
        console.log('bad');
        if (cursor == 29) {
            showGameResult(`The correct answer is：${secret}`);
        } else {
            cursor++;
        }
      }
    }
  } else {
    key = key.toUpperCase();
    if (/^[A-Z]$/.test(key)) {
      aLetter[cursor].innerText = key;
      aLetter[cursor].classList.add('input-letter');
      if (cursor % 5 < 4) cursor++;
    }
  }
}

for(let i=0;i<aButton.length;i++){
  aButton[i].addEventListener('click',function(e){
    keyProcess(e.target.id,e.target.innerText);
  });
}
window.addEventListener('keydown',function(e){
  keyProcess(e.code,e.key)
});