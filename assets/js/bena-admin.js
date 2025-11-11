(function ($) {
    var benaPlaceholderText = window.benaMediaHelpers?.placeholderText || "";
    var benaUseButton = window.benaMediaHelpers?.useButton || "";
    var benaChooseTitle = window.benaMediaHelpers?.chooseTitle || "";
    var benaOptionKey = window.benaMediaHelpers?.optionKey || "";

    var benaFrame;
    var benaWatermarkLastActive = null;
    var benaCurrentWatermarkMode = "disable_watermark";
    var $positionTiles;

    function benaUpdateGridPreviewImage(url) {
        var $grid = $(".bena-position-grid");
        if (!$grid.length) {
            return;
        }
        $grid.attr("data-preview-image", url || "");
        benaRenderPositionPreview();
    }

    function benaUpdatePositionTiles() {
        $positionTiles.each(function () {
            var $tile = $(this).closest(".bena-position-tile");
            if ($(this).is(":checked")) {
                $tile.addClass("bena-position-tile--active");
            } else {
                $tile.removeClass("bena-position-tile--active");
            }
        });
        benaRenderPositionPreview();
    }

    function benaRenderPositionPreview() {
        var $grid = $(".bena-position-grid");
        if (!$grid.length) {
            return;
        }

        var mode = benaCurrentWatermarkMode || "disable_watermark";
        var previewImage = $grid.attr("data-preview-image") || "";
        var previewText = $grid.attr("data-preview-text") || "";
        var previewColor = $grid.attr("data-preview-color") || "#ffffff";
        var previewScale = parseInt($grid.attr("data-preview-scale") || "80", 10);
        previewScale = isNaN(previewScale) ? 80 : Math.max(10, Math.min(100, previewScale));

        var $previews = $grid.find(".bena-position-tile__preview");
        $previews.removeClass("bena-position-tile__preview--visible").empty().css("color", "");

        if (mode === "disable_watermark") {
            return;
        }

        var $activeTile = $grid.find(".bena-position-tile--active");
        if (!$activeTile.length) {
            return;
        }

        var $preview = $activeTile.find(".bena-position-tile__preview");

        if (mode === "enable_watermark" && previewImage) {
            var scaleRatio = previewScale / 100;
            var maxSide = Math.round(48 * scaleRatio);
            $("<img>", { src: previewImage, alt: "", width: maxSide, height: maxSide }).appendTo($preview);
            $preview.css("font-family", "");
            $preview.addClass("bena-position-tile__preview--visible");
        } else if (mode === "enable_watermark_text") {
            if (!previewText) {
                previewText = $("#bena-watermark-text").val() || "";
            }
            var text = $.trim(previewText).substring(0, 24);
            if (text.length) {
                $("<span>", { "class": "bena-position-tile__preview-text", text: text }).appendTo($preview);
                $preview.css({ color: previewColor || "#ffffff" });
                $preview.addClass("bena-position-tile__preview--visible");
            }
        }
    }

    function benaSetSectionDisabled($section, disabled) {
        if (!$section || !$section.length) {
            return;
        }
        var isDisabled = !!disabled;
        $section.toggleClass("bena-watermark-disabled", isDisabled);
        $section.find("input, select, textarea, button").each(function () {
            var $field = $(this);
            if ($field.is('[type="hidden"]')) {
                return;
            }
            if ($field.is('button')) {
                $field.prop('disabled', isDisabled);
            } else if ($field.is('input[type="text"]')) {
                $field.prop('readonly', isDisabled);
            } else if (
                $field.is('input[type="color"]') ||
                $field.is('select') ||
                $field.is('input[type="number"]') ||
                $field.is('input[type="range"]') ||
                $field.is('textarea')
            ) {
                $field.prop('disabled', isDisabled);
            } else {
                $field.prop('disabled', isDisabled);
            }
        });
    }

    function benaUpdateWatermarkVisibility() {
        var selector = "input[name=\"" + benaOptionKey + "[watermark_toggle]\"]";
        var $radios = $(selector);
        var selected = $radios.filter(":checked").val();

        if (!selected) {
            if ($("#bena-toggle-disable_watermark").prop("checked")) {
                selected = "disable_watermark";
            } else if ($("#bena-toggle-enable_watermark").prop("checked")) {
                selected = "enable_watermark";
            } else if ($("#bena-toggle-enable_watermark_text").prop("checked")) {
                selected = "enable_watermark_text";
            }
        }

        var isEnabled = selected && selected !== "disable_watermark";
        var $master = $("#bena-watermark-master");
        if ($master.length) {
            $master.prop("checked", isEnabled);
        }

        var $imageFields = $(".bena-watermark-image");
        var $textFields = $(".bena-watermark-text-fields");

        benaSetSectionDisabled($imageFields, selected !== "enable_watermark");
        benaSetSectionDisabled($textFields, selected !== "enable_watermark_text");

        benaCurrentWatermarkMode = selected || "disable_watermark";
        benaRenderPositionPreview();
    }

    function benaSyncPositionGridFromFields() {
        var $grid = $(".bena-position-grid");
        if (!$grid.length) {
            return;
        }
        var previewImage = $grid.attr("data-preview-image") || "";
        var previewText = $grid.attr("data-preview-text") || "";
        var previewColor = $grid.attr("data-preview-color") || "#ffffff";
        var previewScale = parseInt($("#bena-watermark-scale").val() || "80", 10);
        var previewOpacity = parseInt($("#bena-watermark-opacity").val() || "80", 10);

        previewScale = isNaN(previewScale) ? 80 : Math.max(10, Math.min(100, previewScale));
        previewOpacity = isNaN(previewOpacity) ? 80 : Math.max(0, Math.min(100, previewOpacity));

        $grid.attr("data-preview-image", previewImage);
        $grid.attr("data-preview-text", previewText);
        $grid.attr("data-preview-color", previewColor);
        $grid.attr("data-preview-scale", previewScale);
        $grid.attr("data-preview-opacity", previewOpacity);
    }

    $(function () {
        $positionTiles = $(".bena-position-tile input");
        benaUpdateWatermarkVisibility();
        benaSyncPositionGridFromFields();
        benaUpdatePositionTiles();

        $(document).on("change", "#bena-watermark-master", function () {
            var isChecked = $(this).is(":checked");
            if (!isChecked) {
                benaWatermarkLastActive = $("input[name='" + benaOptionKey + "[watermark_toggle]']").filter(":checked").val();
                $("#bena-toggle-disable_watermark").prop("checked", true).trigger("change");
            } else if (benaWatermarkLastActive && benaWatermarkLastActive !== "disable_watermark") {
                $("#bena-toggle-" + benaWatermarkLastActive).prop("checked", true).trigger("change");
            } else {
                $("#bena-toggle-enable_watermark").prop("checked", true).trigger("change");
            }
        });

        $(document).on("change", "input[name='" + benaOptionKey + "[watermark_toggle]']", function () {
            var value = $(this).val();
            if (value !== "disable_watermark") {
                benaWatermarkLastActive = value;
            }
            benaUpdateWatermarkVisibility();
        });

        $(document).on("input change", "#bena-watermark-scale", function () {
            var value = parseInt($(this).val(), 10);
            if (!isNaN(value)) {
                value = Math.max(10, Math.min(100, value));
                $(this).val(value);
                $("[data-bena-bind='watermark_scale']").text(value + "%");
                $(".bena-position-grid").attr("data-preview-scale", value);
                benaRenderPositionPreview();
            }
        });

        $(document).on("input change", "#bena-watermark-opacity", function () {
            var value = parseInt($(this).val(), 10);
            if (!isNaN(value)) {
                value = Math.max(0, Math.min(100, value));
                $(this).val(value);
                $("[data-bena-bind='watermark_opacity']").text(value + "%");
                $(".bena-position-grid").attr("data-preview-opacity", value);
            }
        });

        $(document).on("input", "#bena-watermark-text", function () {
            var value = $(this).val();
            $(".bena-position-grid").attr("data-preview-text", value);
            benaRenderPositionPreview();
        });

        $(document).on("change", "#bena-watermark-text-color", function () {
            var value = $(this).val();
            $(".bena-position-grid").attr("data-preview-color", value);
            benaRenderPositionPreview();
        });

        $(document).on("click", ".bena-position-tile input", function () {
            benaUpdatePositionTiles();
        });

        benaUpdatePositionTiles();

        $(document).on("click", ".bena-media-upload__choose", function (e) {
            e.preventDefault();
            var $benaContainer = $(this).closest(".bena-media-upload");
            if (benaFrame) {
                benaFrame.open();
                return;
            }

            benaFrame = wp.media({
                title: benaChooseTitle,
                button: { text: benaUseButton },
                library: { type: ["image"] },
                multiple: false
            });

            benaFrame.on("select", function () {
                var benaAttachment = benaFrame.state().get("selection").first().toJSON();
                var benaPreviewUrl = benaAttachment.sizes && benaAttachment.sizes.medium ? benaAttachment.sizes.medium.url : benaAttachment.url;
                $benaContainer.find("input[type=hidden]").val(benaAttachment.id);
                $benaContainer.find(".bena-media-upload__preview").html($("<img>").attr("src", benaPreviewUrl));
                $benaContainer.find(".bena-media-upload__remove").show();
                benaUpdateGridPreviewImage(benaPreviewUrl);
            });

            benaFrame.open();
        });

        $(document).on("click", ".bena-media-upload__remove", function (e) {
            e.preventDefault();
            var $benaContainer = $(this).closest(".bena-media-upload");
            $benaContainer.find("input[type=hidden]").val(0);
            $benaContainer.find(".bena-media-upload__preview").html(
                $("<span>", { "class": "bena-media-upload__placeholder", text: benaPlaceholderText })
            );
            $(this).hide();
            benaUpdateGridPreviewImage("");
        });
    });
})(jQuery);
