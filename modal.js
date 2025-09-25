document.addEventListener("DOMContentLoaded", function () {
    const teaserBtn = document.getElementById("openModal");
    const videoModal = document.getElementById("videoModal");
    const videoFrame = document.getElementById("videoFrame");
    const closeBtn = document.querySelector(".video-close");
    const videoUrl = "https://www.youtube.com/embed/EVfKR55rcPM?autoplay=1";

    if (teaserBtn) {
        teaserBtn.addEventListener("click", function () {
            videoFrame.src = videoUrl; // load video with autoplay
            videoModal.style.display = "flex";
        });
    }

    // Close modal when X is clicked
    closeBtn.addEventListener("click", function () {
        videoModal.style.display = "none";
        videoFrame.src = ""; // stop video
    });

    // Close modal when clicking outside content
    window.addEventListener("click", function (e) {
        if (e.target === videoModal) {
            videoModal.style.display = "none";
            videoFrame.src = "";
        }
    });
});
