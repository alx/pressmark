CmdUtils.CreateCommand({
  name: "press",
  description: "Posts selected content from the current page to Pressmark.",
  help: "Posts selected content from the current page to Pressmark.",
  icon: "http://bookmark.alexgirard.com/favicon.ico",
  homepage: "http://bookmark.alexgirard.com",
  author: { name: "Alex Girard", email: "alx.girard@gmail.com"},
  contributors: ["Alex Payne"],
  license: "WTFPL",
  takes: {"link description": noun_arb_text},
  execute: function(directObj) {
    var d = Application.activeWindow.activeTab.document;
    var e = encodeURIComponent;

	var post_text = directObj.text || context.focusedWindow.getSelection();
	var post_url = d.location.href;
	var post_title = d.title;

    var url = 'http://bookmark.alexgirard.com/index.php?posttext="' + post_text + '"&posturl=' + e(post_url) + '&posttitle=' + e(post_title);

	Utils.openUrlInBrowser(url);
  }
})

