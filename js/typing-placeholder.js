document.addEventListener("DOMContentLoaded", function () {
  const lines = [
    "What are you looking for?",
    "Cu ce te putem ajuta astazi?",
    "Qu'est-ce que tu cherches?",
    "Wonach suchst du?",
    "Mit keresel ma?",
    "Cosa stai cercando oggi?",
    "Какво търсиш днес?",
    "Bugün ne arıyorsunuz?",
    "Τι ψάχνεις σήμερα;",
  ];

  const searchInput = document.querySelector(".s.wd-search-inited");

  if (!searchInput) return; // Exit if the input does not exist

  let lineIndex = 0;
  let charIndex = 0;
  const typingSpeed = 100; // Typing speed in ms
  const pauseTime = 5000; // Pause time between lines in ms

  function typePlaceholder() {
    if (charIndex < lines[lineIndex].length) {
      searchInput.placeholder += lines[lineIndex][charIndex];
      charIndex++;
      setTimeout(typePlaceholder, typingSpeed);
    } else {
      setTimeout(() => {
        searchInput.placeholder = "";
        charIndex = 0;
        lineIndex = (lineIndex + 1) % lines.length;
        typePlaceholder();
      }, pauseTime);
    }
  }

  typePlaceholder();
});
