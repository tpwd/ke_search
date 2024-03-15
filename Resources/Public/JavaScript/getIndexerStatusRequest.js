import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

setInterval(function () {
  new AjaxRequest(TYPO3.settings.ajaxUrls.kesearch_indexerstatus_getstatus)
    .get()
    .then(async function (response) {
      const indexerStatus = await response.resolve();
      document.getElementById("kesearch-indexer-status").innerHTML = indexerStatus.html;
      if (indexerStatus.running === true) {
        let hideElements= ["kesearch-indexer-overview", "kesearch-button-start-full", "kesearch-indexer-report", "kesearch-button-start-incremental"];
        hideElements.forEach((element) => {
          if (document.getElementById(element)) {
            document.getElementById(element).style.display = 'none';
          }
        });
      }
    });
}, 1000);
