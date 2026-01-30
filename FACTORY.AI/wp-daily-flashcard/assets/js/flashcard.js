js
(function ($) {
  function initFlashcard() {
    if (!window.WPDF_DATA || !WPDF_DATA.word) {
      return;
    }

    var $overlay = $("#wpdf-overlay");
    var $modal = $("#wpdf-modal");
    var $textEn = $("#wpdf-text-en");
    var $textZh = $("#wpdf-text-zh");
    var $imageContainer = $("#wpdf-image-container");
    var $message = $("#wpdf-message");
    var $generate = $("#wpdf-generate");

    var word = WPDF_DATA.word;
    var order = WPDF_DATA.order || "en_zh";

    // 設定文字顯示順序
    if (order === "zh_en") {
      $textZh.insertBefore($textEn);
    } else {
      $textEn.insertBefore($textZh);
    }

    $textEn.text(word.en || "");
    $textZh.text(word.zh || "");

    // 若沒有 API key，顯示提示
    if (!WPDF_DATA.has_api) {
      $message
        .text("尚未設定 OpenAI API Key，無法產生圖片，但仍可閱讀文字。")
        .addClass("wpdf-message--error");
      $generate.prop("disabled", true);
    }

    // 顯示 modal
    $overlay.show();
    $modal.show();

    // 關閉按鈕
    $(".wpdf-close, #wpdf-overlay").on("click", function () {
      $overlay.fadeOut(150);
      $modal.fadeOut(150);
    });

    // 產生圖片
    $generate.on("click", function () {
      if (!WPDF_DATA.has_api) return;

      $generate.prop("disabled", true).text("產生中...");
      $message.removeClass("wpdf-message--error wpdf-message--success").text("");

      $.ajax({
        url: WPDF_DATA.ajax_url,
        method: "POST",
        dataType: "json",
        data: {
          action: "wpdf_generate_image",
          nonce: WPDF_DATA.nonce,
          en: word.en,
          zh: word.zh || "",
        },
      })
        .done(function (res) {
          if (!res || !res.success || !res.data || !res.data.url) {
            $message
              .text("圖片產生失敗，請稍後再試。")
              .addClass("wpdf-message--error");
            return;
          }

          var img = $('<img alt="">');
          img.attr("src", res.data.url);
          img.attr(
            "alt",
            "Generated image for word " + (word.en || "") + " " + (word.zh || "")
          );

          $imageContainer.empty().append(img);
          $message
            .text("圖片產生完成！")
            .addClass("wpdf-message--success");
        })
        .fail(function (xhr) {
          var msg =
            (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            "無法產生圖片，請檢查 API Key 或稍後再試。";
          $message.text(msg).addClass("wpdf-message--error");
        })
        .always(function () {
          $generate.prop("disabled", false).text("產生圖片");
        });
    });
  }

  $(document).ready(initFlashcard);
})(jQuery);

