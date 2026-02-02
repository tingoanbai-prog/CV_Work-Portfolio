function openModal() {
    document.getElementById('modal').classList.add('active');
}//將id為modal 的div class新增active使其觸發css中display:block並顯示於畫面上
function closeModal() {
    document.getElementById('modal').classList.remove('active');
}//將id為modal 的div class刪掉active使其觸發css中display:none並從畫面上移除