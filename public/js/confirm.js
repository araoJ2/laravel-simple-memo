
function deleteHandle(event){
    event.preventDefault();
    if(window.confirm('本当に削除してもよろしいですか？')){
        document.getElementById('delete-form').submit();
    }else{
        alert('キャンセルしました')
    }
}