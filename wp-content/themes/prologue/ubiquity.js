CmdUtils.CreateCommand({
  name: "press",
  description: "Posts selected content from the current page to Pressmark.",
  help: "Posts selected content from the current page to Pressmark.",
  icon: "http://bookmark.alexgirard.com/favicon.png",
  homepage: "http://bookmark.alexgirard.com",
  author: { name: "Alex Girard", email: "alex@lasindias.com"},
  contributors: ["Alex Payne"],
  license: "WTFPL",
  takes: {"link description": noun_arb_text},
  execute: function(directObj) {
    var d = Application.activeWindow.activeTab.document;
    var e = encodeURIComponent;

	var post_text = context.focusedWindow.getSelection();
	var post_url = directObj.text || d.location.href;
	var post_title = d.title;

    var url = 'http://bookmark.alexgirard.com/index.php?posttext=' + e(post_text) + '&posturl=' + e(post_url) + '&posttitle=' + e(post_title);

	Utils.openUrlInBrowser(url);
  }
})

