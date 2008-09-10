CmdUtils.CreateCommand({
  name: "press",
  description: "Posts selected content from the current page to Pressmark.",
  help: "Posts selected content from the current page to Pressmark.",
  icon: "http://bookmark.alexgirard.com/favicon.png",
  homepage: "http://bookmark.alexgirard.com",
  author: { name: "Alex Girard", email: "alex@lasindias.com"},
  contributors: ["Alex Payne"],
  license: "WTFPL",
  execute: function() {
    var d = Application.activeWindow.activeTab.document;
    var w = context.focusedWindow;
    var sel = w.getSelection();
    var e = encodeURIComponent;

    var url = 'http://bookmark.alexgirard.com/index.php?posttext=' + e(sel) + '&posturl=' + e(d.location.href) + '&posttitle=' + e(d.title);

	Utils.openUrlInBrowser(url);
  }
})

