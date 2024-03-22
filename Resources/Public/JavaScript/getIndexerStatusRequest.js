import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

setInterval(function () {
  new AjaxRequest(TYPO3.settings.ajaxUrls.kesearch_indexerstatus_getstatus)
    .get()
    .then(async function (response) {
      const indexerStatus = await response.resolve();
      document.getElementById("kesearch-indexer-status").innerHTML = indexerStatus.html;
      if (indexerStatus.running === true) {
        let hideElements= ["kesearch-indexer-overview", "kesearch-button-start-full", "kesearch-indexer-report", "kesearch-button-start-incremental", "kesearch-button-reload"];
        hideElements.forEach((element) => {
          if (document.getElementById(element)) {
            document.getElementById(element).style.display = 'none';
          }
        });
      } else {
        let hideElements= ["kesearch-indexer-running-warning"];
        hideElements.forEach((element) => {
          if (document.getElementById(element)) {
            document.getElementById(element).style.display = 'none';
          }
        });
        let showElements= ["kesearch-button-reload"];
        showElements.forEach((element) => {
          if (document.getElementById(element)) {
            document.getElementById(element).style.display = 'inline-flex';
          }
        });
      }
    });
}, 1000);
