CmdUtils.CreateCommand({
  name: "press",
  description: "Posts content from the current page to Pressmark.",
  icon: "<?php bloginfo('template_url'); ?>/favicon.png",
  homepage: "<?php bloginfo('template_url'); ?>/ubiquity.html",
  author: { name: "Alex Payne", email: "al3x@al3x.net"},
  contributors: ["Alex Girard"],
  license: "WTFPL",
  execute: function() {
    var d = Application.activeWindow.activeTab.document;
    var w = context.focusedWindow;
    var di = d.images;
    var dom = d.location.href.match(/(.*\/\/[^\/]*)\/.*/)[1];
    var sel = w.getSelection();
    var e = encodeURIComponent;

    var i='';    
    for (var n=0; n < di.length; n++) {
      if (di[n].offsetWidth * di[n].offsetHeight > 70 * 70) {
        i = di[n].src.replace(dom, '@@') + '|' + di[n].offsetWidth + '|' + di[n].offsetHeight + ',';
      }
    }

    var url = '<?php bloginfo('site_url'); ?>/index.php?posttext=' + e(sel) + '&posturl=' + e(d.location.href) + '&posttitle=' + e(d.title);

    CmdUtils.getHiddenWindow().open(url, 'press', 'toolbar = 0, resizable = 1, scrollbars = yes, status = 1, width = 450, height = 400');
  }
})

