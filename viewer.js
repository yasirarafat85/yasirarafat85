const url = 'sample.pdf'; // আপনার PDF ফাইলের নাম

const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');

pdfjsLib.getDocument(url).promise.then(pdf => {
  // শুধু প্রথম পেজ দেখানো
  pdf.getPage(1).then(page => {
    const viewport = page.getViewport({ scale: 1.5 });
    canvas.height = viewport.height;
    canvas.width = viewport.width;

    page.render({
      canvasContext: ctx,
      viewport: viewport
    });
  });
});
