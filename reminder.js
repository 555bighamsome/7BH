/* Pre-study setup reminder. */
(function(){
"use strict";

const data = window.StudyData;
data?.init({stage:"reminder"});

document.getElementById("reminder-continue")?.addEventListener("click", async event => {
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = "Opening tutorial...";
  data?.record("reminder_acknowledged");
  await data?.navigate("tutorial.html");
});
})();
