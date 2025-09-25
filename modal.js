
  const modal = document.getElementById("videoModal");
  const openModal = document.getElementById("openModal");
  const closeModal = document.querySelector(".close");
  const youtubeVideo = document.getElementById("youtubeVideo");

  const videoURL = "https://www.youtube.com/embed/EVfKR55rcPM?autoplay=1";

  openModal.addEventListener("click", () => {
    modal.style.display = "block";
    youtubeVideo.src = videoURL;
  });

  closeModal.addEventListener("click", () => {
    modal.style.display = "none";
    youtubeVideo.src = ""; // stop video
  });

  window.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.style.display = "none";
      youtubeVideo.src = "";
    }
  });
