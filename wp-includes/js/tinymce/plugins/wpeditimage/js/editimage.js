var tinymce=null,tinyMCEPopup,tinyMCE,wpImage;tinyMCEPopup={init:function(){var b=this,a,c;a=b.getWin();tinymce=a.tinymce;tinyMCE=a.tinyMCE;b.editor=tinymce.EditorManager.activeEditor;b.params=b.editor.windowManager.params;b.features=b.editor.windowManager.features;b.dom=b.editor.windowManager.createInstance("tinymce.dom.DOMUtils",document);b.editor.windowManager.onOpen.dispatch(b.editor.windowManager,window)},getWin:function(){return(!window.frameElement&&window.dialogArguments)||opener||parent||top},getParam:function(b,a){return this.editor.getParam(b,a)},close:function(){var a=this;function b(){a.editor.windowManager.close(window);tinymce=tinyMCE=a.editor=a.params=a.dom=a.dom.doc=null}if(tinymce.isOpera){a.getWin().setTimeout(b,0)}else{b()}},execCommand:function(d,c,e,b){b=b||{};b.skip_focus=1;this.restoreSelection();return this.editor.execCommand(d,c,e,b)},storeSelection:function(){this.editor.windowManager.bookmark=tinyMCEPopup.editor.selection.getBookmark(1)},restoreSelection:function(){var a=tinyMCEPopup;if(tinymce.isIE){a.editor.selection.moveToBookmark(a.editor.windowManager.bookmark)}}};tinyMCEPopup.init();wpImage={preInit:function(){var a=tinyMCEPopup.editor,e=tinyMCEPopup.getWin(),d=e.document.styleSheets,b,c;for(c=0;c<d.length;c++){b=d.item(c).href;if(b&&b.indexOf("colors")!=-1){document.getElementsByTagName("head")[0].appendChild(a.dom.create("link",{rel:"stylesheet",href:b}));break}}},I:function(a){return document.getElementById(a)},current:"",link:"",link_rel:"",target_value:"",current_size_sel:"s100",width:"",height:"",align:"",img_alt:"",setTabs:function(b){var a=this;if("current"==b.className){return false}a.I("div_advanced").style.display=("tab_advanced"==b.id)?"block":"none";a.I("div_basic").style.display=("tab_basic"==b.id)?"block":"none";a.I("tab_basic").className=a.I("tab_advanced").className="";b.className="current";return false},img_seturl:function(b){var c=this,a=c.I("link_rel").value;if("current"==b){c.I("link_href").value=c.current;c.I("link_rel").value=c.link_rel}else{c.I("link_href").value=c.link;if(a){a=a.replace(/attachment|wp-att-[0-9]+/gi,"");c.I("link_rel").value=tinymce.trim(a)}}},imgAlignCls:function(b){var c=this,a=c.I("img_classes").value;c.I("img_demo").className=c.align=b;a=a.replace(/align[^ "']+/gi,"");a+=(" "+b);a=a.replace(/\s+/g," ").replace(/^\s/,"");if("aligncenter"==b){c.I("hspace").value="";c.updateStyle("hspace")}c.I("img_classes").value=a},showSize:function(e){var c=this,f=c.I("img_demo"),a=c.width,d=c.height,g=e.id||"s100",b;b=parseInt(g.substring(1))/200;f.width=Math.round(a*b);f.height=Math.round(d*b);c.showSizeClear();e.style.borderColor="#A3A3A3";e.style.backgroundColor="#E5E5E5"},showSizeSet:function(){var b=this,d,c,a;if((b.width*1.3)>parseInt(b.preloadImg.width)){d=b.I("s130"),c=b.I("s120"),a=b.I("s110");d.onclick=c.onclick=a.onclick=null;d.onmouseover=c.onmouseover=a.onmouseover=null;d.style.color=c.style.color=a.style.color="#aaa"}},showSizeRem:function(){var a=this,c=a.I("img_demo"),b=document.forms[0];c.width=Math.round(b.width.value*0.5);c.height=Math.round(b.height.value*0.5);a.showSizeClear();a.I(a.current_size_sel).style.borderColor="#A3A3A3";a.I(a.current_size_sel).style.backgroundColor="#E5E5E5";return false},showSizeClear:function(){var b=this.I("img_size").getElementsByTagName("div"),a;for(a=0;a<b.length;a++){b[a].style.borderColor="#f1f1f1";b[a].style.backgroundColor="#f1f1f1"}},imgEditSize:function(g){var d=this,i=document.forms[0],a,c,b,e,j;if(!d.preloadImg||!d.preloadImg.width||!d.preloadImg.height){return}a=parseInt(d.preloadImg.width),c=parseInt(d.preloadImg.height),b=d.width||a,e=d.height||c,j=g.id||"s100";size=parseInt(j.substring(1))/100;b=Math.round(b*size);e=Math.round(e*size);i.width.value=Math.min(a,b);i.height.value=Math.min(c,e);d.current_size_sel=j;d.demoSetSize()},demoSetSize:function(a){var c=this.I("img_demo"),b=document.forms[0];c.width=b.width.value?Math.round(b.width.value*0.5):"";c.height=b.height.value?Math.round(b.height.value*0.5):""},demoSetStyle:function(){var b=document.forms[0],a=this.I("img_demo"),c=tinyMCEPopup.editor.dom;if(a){c.setAttrib(a,"style",b.img_style.value);c.setStyle(a,"width","");c.setStyle(a,"height","")}},origSize:function(){var a=this,c=document.forms[0],b=a.I("s100");c.width.value=a.width=a.preloadImg.width;c.height.value=a.height=a.preloadImg.height;a.showSizeSet();a.demoSetSize();a.showSize(b)},init:function(){var a=tinyMCEPopup.editor,b;b=document.body.innerHTML;document.body.innerHTML=a.translate(b);window.setTimeout(function(){wpImage.setup()},500)},setup:function(){var q=this,l,b,m,e,j=document.forms[0],i=tinyMCEPopup.editor,k=q.I("img_demo"),h=tinyMCEPopup.dom,a,g,p="",o,n;document.dir=tinyMCEPopup.editor.getParam("directionality","");if(tinyMCEPopup.editor.getParam("wpeditimage_disable_captions",false)){q.I("cap_field").style.display="none"}tinyMCEPopup.restoreSelection();b=i.selection.getNode();if(b.nodeName!="IMG"){return}j.img_src.value=k.src=m=i.dom.getAttrib(b,"src");i.dom.setStyle(b,"float","");q.getImageData();l=i.dom.getAttrib(b,"class");if(a=h.getParent(b,"dl")){o=i.dom.getAttrib(a,"class");o=o.match(/align[^ "']+/i);if(o&&!h.hasClass(b,o)){l+=" "+o;tinymce.trim(l)}g=i.dom.select("dd.wp-caption-dd",a);if(g&&g[0]){p=i.serializer.serialize(g[0]).replace(/^<p>/,"").replace(/<\/p>$/,"")}}j.img_cap_text.value=p;j.img_title.value=i.dom.getAttrib(b,"title");j.img_alt.value=i.dom.getAttrib(b,"alt");j.border.value=i.dom.getAttrib(b,"border");j.vspace.value=i.dom.getAttrib(b,"vspace");j.hspace.value=i.dom.getAttrib(b,"hspace");j.align.value=i.dom.getAttrib(b,"align");j.width.value=q.width=i.dom.getAttrib(b,"width");j.height.value=q.height=i.dom.getAttrib(b,"height");j.img_classes.value=l;j.img_style.value=i.dom.getAttrib(b,"style");if(h.getAttrib(b,"hspace")){q.updateStyle("hspace")}if(h.getAttrib(b,"border")){q.updateStyle("border")}if(h.getAttrib(b,"vspace")){q.updateStyle("vspace")}if(n=i.dom.getParent(b,"A")){j.link_href.value=q.current=i.dom.getAttrib(n,"href");j.link_title.value=i.dom.getAttrib(n,"title");j.link_rel.value=q.link_rel=i.dom.getAttrib(n,"rel");j.link_style.value=i.dom.getAttrib(n,"style");q.target_value=i.dom.getAttrib(n,"target");j.link_classes.value=i.dom.getAttrib(n,"class")}j.link_target.checked=(q.target_value&&q.target_value=="_blank")?"checked":"";e=m.substring(m.lastIndexOf("/"));e=e.replace(/-[0-9]{2,4}x[0-9]{2,4}/,"");q.link=m.substring(0,m.lastIndexOf("/"))+e;if(l.indexOf("alignleft")!=-1){q.I("alignleft").checked="checked";k.className=q.align="alignleft"}else{if(l.indexOf("aligncenter")!=-1){q.I("aligncenter").checked="checked";k.className=q.align="aligncenter"}else{if(l.indexOf("alignright")!=-1){q.I("alignright").checked="checked";k.className=q.align="alignright"}else{if(l.indexOf("alignnone")!=-1){q.I("alignnone").checked="checked";k.className=q.align="alignnone"}}}}if(q.width&&q.preloadImg.width){q.showSizeSet()}document.body.style.display=""},remove:function(){var a=tinyMCEPopup.editor,c,b;tinyMCEPopup.restoreSelection();b=a.selection.getNode();if(b.nodeName!="IMG"){return}if((c=a.dom.getParent(b,"div"))&&a.dom.hasClass(c,"mceTemp")){a.dom.remove(c)}else{if((c=a.dom.getParent(b,"A"))&&c.childNodes.length==1){a.dom.remove(c)}else{a.dom.remove(b)}}a.execCommand("mceRepaint");tinyMCEPopup.close();return},update:function(){var m=this,v=document.forms[0],g=tinyMCEPopup.editor,e,y,d=null,n,h,p,r,o=null,k=v.img_classes.value,l,q,u="",j,i,s,a,B,x="",c,z,w;tinyMCEPopup.restoreSelection();e=g.selection.getNode();if(e.nodeName!="IMG"){return}if(v.img_src.value===""){m.remove();return}if(v.img_cap_text.value!=""&&v.width.value!=""){o=1;k=k.replace(/align[^ "']+\s?/gi,"")}p=g.dom.getParent(e,"a");h=g.dom.getParent(e,"p");n=g.dom.getParent(e,"dl");r=g.dom.getParent(e,"div");tinyMCEPopup.execCommand("mceBeginUndoLevel");if(v.width.value!=e.width||v.height.value!=e.height){k=k.replace(/size-[^ "']+/,"")}g.dom.setAttribs(e,{src:v.img_src.value,title:v.img_title.value,alt:v.img_alt.value,width:v.width.value,height:v.height.value,style:v.img_style.value,"class":k});if(v.link_href.value){if(p==null){if(!v.link_href.value.match(/https?:\/\//i)){v.link_href.value=tinyMCEPopup.editor.documentBaseURI.toAbsolute(v.link_href.value)}g.getDoc().execCommand("unlink",false,null);tinyMCEPopup.execCommand("mceInsertLink",false,"#mce_temp_url#",{skip_undo:1});tinymce.each(g.dom.select("a"),function(b){if(g.dom.getAttrib(b,"href")=="#mce_temp_url#"){g.dom.setAttribs(b,{href:v.link_href.value,title:v.link_title.value,rel:v.link_rel.value,target:(v.link_target.checked==true)?"_blank":"","class":v.link_classes.value,style:v.link_style.value})}})}else{g.dom.setAttribs(p,{href:v.link_href.value,title:v.link_title.value,rel:v.link_rel.value,target:(v.link_target.checked==true)?"_blank":"","class":v.link_classes.value,style:v.link_style.value})}}if(o){a=10+parseInt(v.width.value);B=(m.align=="aligncenter")?"mceTemp mceIEcenter":"mceTemp";w=v.img_cap_text.value;w=w.replace(/\r\n|\r/g,"\n").replace(/<[a-zA-Z0-9]+( [^<>]+)?>/g,function(b){return b.replace(/[\r\n\t]+/," ")});w=w.replace(/\s*\n\s*/g,"<br />");if(n){g.dom.setAttribs(n,{"class":"wp-caption "+m.align,style:"width: "+a+"px;"});if(r){g.dom.setAttrib(r,"class",B)}if((i=g.dom.getParent(e,"dt"))&&(s=i.nextSibling)&&g.dom.hasClass(s,"wp-caption-dd")){g.dom.setHTML(s,w)}}else{if((q=v.img_classes.value.match(/wp-image-([0-9]{1,6})/))&&q[1]){u="attachment_"+q[1]}if(v.link_href.value&&(x=g.dom.getParent(e,"a"))){if(x.childNodes.length==1){l=g.dom.getOuterHTML(x)}else{l=g.dom.getOuterHTML(x);l=l.match(/<a [^>]+>/i);l=l+g.dom.getOuterHTML(e)+"</a>"}}else{l=g.dom.getOuterHTML(e)}l='<dl id="'+u+'" class="wp-caption '+m.align+'" style="width: '+a+'px"><dt class="wp-caption-dt">'+l+'</dt><dd class="wp-caption-dd">'+w+"</dd></dl>";j=g.dom.create("div",{"class":B},l);if(h){h.parentNode.insertBefore(j,h);if(h.childNodes.length==1){g.dom.remove(h)}else{if(x&&x.childNodes.length==1){g.dom.remove(x)}else{g.dom.remove(e)}}}else{if(c=g.dom.getParent(e,"TD,TH,LI")){c.appendChild(j);if(x&&x.childNodes.length==1){g.dom.remove(x)}else{g.dom.remove(e)}}}}}else{if(n&&r){if(v.link_href.value&&(z=g.dom.getParent(e,"a"))){l=g.dom.getOuterHTML(z)}else{l=g.dom.getOuterHTML(e)}h=g.dom.create("p",{},l);r.parentNode.insertBefore(h,r);g.dom.remove(r)}}if(v.img_classes.value.indexOf("aligncenter")!=-1){if(h&&(!h.style||h.style.textAlign!="center")){g.dom.setStyle(h,"textAlign","center")}}else{if(h&&h.style&&h.style.textAlign=="center"){g.dom.setStyle(h,"textAlign","")}}if(!v.link_href.value&&p){y=g.selection.getBookmark();g.dom.remove(p,1);g.selection.moveToBookmark(y)}tinyMCEPopup.execCommand("mceEndUndoLevel");g.execCommand("mceRepaint");tinyMCEPopup.close()},updateStyle:function(a){var e=tinyMCEPopup.dom,c,d=document.forms[0],b=e.create("img",{style:d.img_style.value});if(tinyMCEPopup.editor.settings.inline_styles){if(a=="align"){e.setStyle(b,"float","");e.setStyle(b,"vertical-align","");c=d.align.value;if(c){if(c=="left"||c=="right"){e.setStyle(b,"float",c)}else{b.style.verticalAlign=c}}}if(a=="border"){e.setStyle(b,"border","");c=d.border.value;if(c||c=="0"){if(c=="0"){b.style.border="0"}else{b.style.border=c+"px solid black"}}}if(a=="hspace"){e.setStyle(b,"marginLeft","");e.setStyle(b,"marginRight","");c=d.hspace.value;if(c){b.style.marginLeft=c+"px";b.style.marginRight=c+"px"}}if(a=="vspace"){e.setStyle(b,"marginTop","");e.setStyle(b,"marginBottom","");c=d.vspace.value;if(c){b.style.marginTop=c+"px";b.style.marginBottom=c+"px"}}d.img_style.value=e.serializeStyle(e.parseStyle(b.style.cssText));this.demoSetStyle()}},checkVal:function(a){if(a.value==""){if(a.id=="img_src"){a.value=this.I("img_demo").src||this.preloadImg.src}}},resetImageData:function(){var a=document.forms[0];a.width.value=a.height.value=""},updateImageData:function(){var d=document.forms[0],b=wpImage,a=d.width.value,c=d.height.value;if(!a&&c){a=d.width.value=b.width=Math.round(b.preloadImg.width/(b.preloadImg.height/c))}else{if(a&&!c){c=d.height.value=b.height=Math.round(b.preloadImg.height/(b.preloadImg.width/a))}}if(!a){d.width.value=b.width=b.preloadImg.width}if(!c){d.height.value=b.height=b.preloadImg.height}b.showSizeSet();b.demoSetSize();if(d.img_style.value){b.demoSetStyle()}},getImageData:function(){var a=wpImage,b=document.forms[0];a.preloadImg=new Image();a.preloadImg.onload=a.updateImageData;a.preloadImg.onerror=a.resetImageData;a.preloadImg.src=tinyMCEPopup.editor.documentBaseURI.toAbsolute(b.img_src.value)}};window.onload=function(){wpImage.init()};wpImage.preInit();