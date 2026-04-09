// Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('../sw.js')
      .then((reg) => {
        console.log('LifeStore Service Worker: Success!', reg.scope);
      })
      .catch((err) => {
        console.log('LifeStore Service Worker: Connection Failed!', err);
      });
  });
}

// Installation Prompt Logic
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent Chrome 67 and earlier from automatically showing the prompt
  e.preventDefault();
  // Stash the event so it can be triggered later.
  deferredPrompt = e;
  
  // You can show a custom install button here if you want
  console.log('LifeStore: App is installable!');
});

// To trigger install manually (e.g., from a button):
function installLifeStore() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('User accepted the LifeStore install prompt');
      }
      deferredPrompt = null;
    });
  }
}
