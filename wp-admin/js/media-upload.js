var wpActiveEditor;function send_to_editor(b){var a;if(!wpActiveEditor){if(typeof(tinymce)!="undefined"&&tinymce.activeEditor){a=tinymce.activeEditor;wpActiveEditor=a.id}else{return false}}if(!a&&typeof(tinymce)!="undefined"&&wpActiveEditor){a=tinymce.get(wpActiveEditor)}if(a&&!a.isHidden()){if(tinymce.isIE&&a.windowManager.insertimagebookmark){a.selection.moveToBookmark(a.windowManager.insertimagebookmark)}if(b.indexOf("[caption")===0){if(a.plugins.wpeditimage){b=a.plugins.wpeditimage._do_shcode(b)}}else{if(b.indexOf("[gallery")===0){if(a.plugins.wpgallery){b=a.plugins.wpgallery._do_gallery(b)}}else{if(b.indexOf("[embed")===0){if(a.plugins.wordpress){b=a.plugins.wordpress._setEmbed(b)}}}}a.execCommand("mceInsertContent",false,b)}else{if(typeof(QTags)!="undefined"){QTags.insertContent(b)}else{document.getElementById(wpActiveEditor).value+=b}}tb_remove()}var tb_position;(function(a){tb_position=function(){var f=a("#TB_window"),e=a(window).width(),d=a(window).height(),c=(720<e)?720:e,b=0;if(a("body.admin-bar").length){b=28}if(f.size()){f.width(c-50).height(d-45-b);a("#TB_iframeContent").width(c-50).height(d-75-b);f.css({"margin-left":"-"+parseInt(((c-50)/2),10)+"px"});if(typeof document.body.style.maxWidth!="undefined"){f.css({top:20+b+"px","margin-top":"0"})}}return a("a.thickbox").each(function(){var g=a(this).attr("href");if(!g){return}g=g.replace(/&width=[0-9]+/g,"");g=g.replace(/&height=[0-9]+/g,"");a(this).attr("href",g+"&width="+(c-80)+"&height="+(d-85-b))})};a(window).resize(function(){tb_position()});a(document).ready(function(b){b("a.thickbox").click(function(){var c;if(typeof(tinymce)!="undefined"&&tinymce.isIE&&(c=tinymce.get(wpActiveEditor))&&!c.isHidden()){c.focus();c.windowManager.insertimagebookmark=c.selection.getBookmark()}})})})(jQuery);