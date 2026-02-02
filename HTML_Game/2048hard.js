function openalert(){
    document.getElementById('customAlert').classList.add('active');
}
var board;
var rows=4;//set the voard to be4*4
var columns=4;
function updateTile(tile,num){
    tile.innerText="";
    tile.classList.value="";
    tile.classList.add("tile");
    if(num>0){
        tile.innerText=num;;
        if(num<=4096){
            tile.classList.add("x"+num.toString());
        }else{
            tile.classList.add("x8192");
        }
        if (num === 8192) {
            setTimeout(() => {
                openalert();
            }, 100);
        }
    }
}
function setGame(){
    board=[           //set the initial board 
        [0,0,0,0],
        [0,0,0,0],
        [0,0,0,0],
        [0,0,0,0]
    ]
    for(let r=0;r<rows;r++){
        for(let c=0;c<columns;c++){
            //div<id"0-0"></div>
            let tile=document.createElement("div");//let each element in the board with id 0-0,1-0,2-0 and so on 
            tile.id=r.toString()+"-"+c.toString();
            let num=board[r][c];
            updateTile(tile,num);
            document.getElementById("board").append(tile);
        }
    }
    setNumber();
    setNumber();
}
function hasEmptyTile(){
    for(let r=0;r<rows;r++){
        for(let c=0;c<columns;c++){
            if(board[r][c]==0){
                return true;
            }
        }
    }
}
function setNumber(){
    if(!hasEmptyTile()){
        return;
    }
    let found=false;
    while(!found){
        let r=Math.floor(Math.random()*rows);
        let c=Math.floor(Math.random()*columns);
        if(board[r][c]==0){
            board[r][c] = Math.random() < 0.9 ? 2 : 4;
            let tile=document.getElementById(r.toString()+"-"+c.toString());
            if(board[r][c]===2){
                tile.innerText="2";
                tile.classList.add("x2");
            }else if(board[r][c]===4){
                tile.innerText="4";
                tile.classList.add("x4");
            }
            
            found=true;
        }
    }
}
window.onload=function(){
    setGame();
}
function filterzero(row){
    return row.filter(num=>num!=0)//create a new array with no zero
}
function slide(row){  //for example now [0,2,2,2]
    row=filterzero(row);//->remove the 0 =>[2,2,2]
    for(let i=0;i<row.length-1;i++){
        if(row[i]==row[i+1]){
            row[i]*=2;
            row[i+1]=0;
        }//=>[4,0,2]
    }
    row=filterzero(row);//[4,2]
    while(row.length<columns){
        row.push(0);//[4,2,0,0]
    }
    return row;
}
function slideLeft(){
    for(let r=0;r<rows;r++){
        let row=board[r];
        row=slide(row);
        board[r]=row;
        for(let c=0;c<columns;c++){
            let tile=document.getElementById(r.toString()+"-"+c.toString());
            let num=board[r][c];
            updateTile(tile,num)
        }
    }
    
}
function slideRight(){
    for(let r=0;r<rows;r++){
        let row=board[r];
        row.reverse();
        row=slide(row);
        row.reverse();
        board[r]=row;
        for(let c=0;c<columns;c++){
            let tile=document.getElementById(r.toString()+"-"+c.toString());
            let num=board[r][c];
            updateTile(tile,num)
        }
    }
    
}
function slideUp(){
    for(let c=0;c<columns;c++){
        let row=[board[0][c],board[1][c],board[2][c],board[3][c]];
        row=slide(row);
        board[0][c]=row[0];
        board[1][c]=row[1];
        board[2][c]=row[2];
        board[3][c]=row[3];
        for(let r=0;r<rows;r++){
            let tile=document.getElementById(r.toString()+"-"+c.toString());
            let num=board[r][c];
            updateTile(tile,num)
        }
    }
    
}
function slideDown(){
    for(let c=0;c<columns;c++){
        let row=[board[0][c],board[1][c],board[2][c],board[3][c]];
        row.reverse();
        row=slide(row);
        row.reverse();
        board[0][c]=row[0];
        board[1][c]=row[1];
        board[2][c]=row[2];
        board[3][c]=row[3];
        for(let r=0;r<rows;r++){
            let tile=document.getElementById(r.toString()+"-"+c.toString());
            let num=board[r][c];
            updateTile(tile,num)
        }
    }
    
}

function canSlideLeft() {
    for (let r = 0; r < rows; r++) {
        for (let c = 1; c < columns; c++) { // Start from the second column
            if (board[r][c] !== 0) {
                if (board[r][c - 1] === 0 || board[r][c - 1] === board[r][c]) {
                    return true; // A tile can move or merge left
                }
            }
        }
    }
    return false;
}

function canSlideRight() {
    for (let r = 0; r < rows; r++) {
        for (let c = columns - 2; c >= 0; c--) { // Start from the second-to-last column
            if (board[r][c] !== 0) {
                if (board[r][c + 1] === 0 || board[r][c + 1] === board[r][c]) {
                    return true; // A tile can move or merge right
                }
            }
        }
    }
    return false;
}

function canSlideUp() {
    for (let c = 0; c < columns; c++) {
        for (let r = 1; r < rows; r++) { // Start from the second row
            if (board[r][c] !== 0) {
                if (board[r - 1][c] === 0 || board[r - 1][c] === board[r][c]) {
                    return true; // A tile can move or merge up
                }
            }
        }
    }
    return false;
}

function canSlideDown() {
    for (let c = 0; c < columns; c++) {
        for (let r = rows - 2; r >= 0; r--) { // Start from the second-to-last row
            if (board[r][c] !== 0) {
                if (board[r + 1][c] === 0 || board[r + 1][c] === board[r][c]) {
                    return true; // A tile can move or merge down
                }
            }
        }
    }
    return false;
}

document.addEventListener("keyup", (e) => {
    if (e.code === "ArrowLeft") {
        if (canSlideLeft()) {
            slideLeft();
            setNumber();
        }
    } else if (e.code === "ArrowRight") {
        if (canSlideRight()) {
            slideRight();
            setNumber();
        }
    } else if (e.code === "ArrowUp") {
        if (canSlideUp()) {
            slideUp();
            setNumber();
        }
    } else if (e.code === "ArrowDown") {
        if (canSlideDown()) {
            slideDown();
            setNumber();
        }
    }

    if (checkGameOver()) {
        setTimeout(() => alert("Game Over!"), 200);
    }
});