import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";
import { 
  getAuth, 
  GoogleAuthProvider, 
  signInWithPopup, 
  signInWithRedirect, 
  getRedirectResult 
} from "https://www.gstatic.com/firebasejs/11.6.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyBlnEruMTKybTaBIElBVk8z5pK5xHF6CR8",
  authDomain: "login-9c12c.firebaseapp.com",
  projectId: "login-9c12c",
  storageBucket: "login-9c12c.firebasestorage.app",
  messagingSenderId: "697104751898",
  appId: "1:697104751898:web:8b201b5ebe0aa8859b20a3",
  measurementId: "G-VLGFG4FDNF"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
auth.languageCode = 'en';
const provider = new GoogleAuthProvider();

// Add some additional scopes for better user info
provider.addScope('profile');
provider.addScope('email');

// Check for redirect result on page load
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const result = await getRedirectResult(auth);
    if (result && result.user) {
      // The user was redirected back after Google auth
      // This will trigger our form submission
      const googleSignInEvent = new CustomEvent('googleSignInSuccess', { 
        detail: { user: result.user } 
      });
      document.dispatchEvent(googleSignInEvent);
    }
  } catch (error) {
    console.error("Redirect result check error:", error);
    if (error.code !== 'auth/null-user') {
      const googleSignInErrorEvent = new CustomEvent('googleSignInError', { 
        detail: { error } 
      });
      document.dispatchEvent(googleSignInErrorEvent);
    }
  }
});

// Function to handle Google Sign In
export async function signInWithGoogle() {
  try {
    // First try with popup
    return await signInWithPopup(auth, provider);
  } catch (error) {
    // If popup fails due to being blocked, try with redirect
    if (error.code === 'auth/popup-blocked') {
      console.log("Popup was blocked, trying redirect method instead");
      // Store a flag in sessionStorage to detect redirect return
      sessionStorage.setItem('googleSignInRedirect', 'true');
      // Use redirect method instead
      await signInWithRedirect(auth, provider);
      // This line won't be reached until after redirect completes
      throw new Error("Redirecting to Google authentication...");
    } else {
      // Re-throw other errors
      throw error;
    }
  }
}
  