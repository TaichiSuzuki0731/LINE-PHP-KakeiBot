var $ = jQuery;

$(function() {
    var topBtn = $('#page_top');
    topBtn.hide();
    var hideButtonTimer = null; // タイマー変数を初期化

    // ボタンの表示設定
    $(window).scroll(function() {
        if ($(this).scrollTop() > 1000) {
            // 画面を80pxスクロールしたら、ボタンを表示する
            topBtn.fadeIn();
            if (hideButtonTimer === null) { // タイマーが開始されていない場合にのみ実行
                hideButtonTimer = setTimeout(function() {
                    topBtn.fadeOut();
                    hideButtonTimer = null; // タイマー変数をリセット
                }, 3000); // 3秒後にボタンを非表示にする
            }
        } else {
            // 画面が80pxより上なら、ボタンを表示しない
            topBtn.fadeOut();
            clearTimeout(hideButtonTimer); // タイマーが実行中ならキャンセル
            hideButtonTimer = null; // タイマー変数をリセット
        }
    });

    // ボタンをクリックしたら、スクロールして上に戻る
    topBtn.click(function() {
        $('body,html').animate({
            scrollTop: 0
        }, 500);
        return false;
    });

});
