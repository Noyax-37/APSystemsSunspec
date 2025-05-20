
function whenLoaded() {
    // Display the real version number just before the plugin version number (YYYY-MM-DD hh:mm:ss)
    let sVersion = document.querySelector('#span_plugin_install_date')
    let dateVersion = sVersion.innerText
    sVersion.innerText = version + " (" + dateVersion + ")";
  
  }
  
  // TODO: Remove jQuery $(document) backward compatibility when Core 4.3 deprecated
  if (typeof domUtils !== 'undefined') {
    domUtils(whenLoaded)
  } else {
    $(document).ready(whenLoaded)
  }