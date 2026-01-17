function loadScript( url, callback ) {
  var script = document.createElement( "script" )
  script.type = "text/javascript";
  if(script.readyState) {  //IE
    script.onreadystatechange = function() {
      if ( script.readyState === "loaded" || script.readyState === "complete" ) {
        script.onreadystatechange = null;
        callback();
      }
    };
  } else {  //Others
    script.onload = function() {
      callback();
    };
  }
  script.src = url;
  document.getElementsByTagName( "head" )[0].appendChild( script );
}

function encryptRetLogin(username,pwd,keyurl){	
//alert("encryptRetLogin");
	var bUrl = keyurl;
	//alert("burl-->"+bUrl);
	var callbackFunc = function(publicKey){   
	//alert("publicKey-->"+publicKey);
		try{
		//alert("inside try4");

		var user =username;
		var password = pwd;
		
		jQuery.jCryption.encrypt(user, publicKey, function(encrypted) {
			var encryptedUser = encrypted;
			document.forms[0].Encrypted_Login_ID.value = encryptedUser; 
			var login2=document.forms[0].Encrypted_Login_ID.value;


		});
			jQuery.jCryption.encrypt(password, publicKey, function(encryptedPasswd) {
				var encryptedPassword = encryptedPasswd;
				document.forms[0].pwd.value = encryptedPassword; 
				var pwd2 = document.forms[0].pwd.value;


		});
			
		}catch(Exception){
			alert('Exception-99 Occurred');
		}
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}
function encryptDebitCardLogin(debitcardnum,pinno,deb_captcha,keyurl){	
	var bUrl = keyurl;
	var callbackFunc = function(publicKey){   
		try{

		var user =debitcardnum;
		var password = pinno;
		var captcha = deb_captcha;
		
		jQuery.jCryption.encrypt(user, publicKey, function(encrypted) {
			var encryptedUser = encrypted;
			document.forms[0].Encrypted_DebitCard_No.value = encryptedUser; 

		});
		jQuery.jCryption.encrypt(password, publicKey, function(encryptedPasswd) {
				var encryptedPassword = encryptedPasswd;
				document.forms[0].deb_pinno.value = encryptedPassword; 
				
		});
		
		jQuery.jCryption.encrypt(captcha, publicKey, function(encryptedcap) {
			var encryptedcaptcha=encryptedcap;			
			document.forms[0].Encrypted_Captcha.value = encryptedcaptcha; 
			document.forms[0].deb_captcha.value = null; 

		});
			
		}catch(Exception){
			alert('Exception-99 Occurred');
		}
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}
function encryptNetsecureRegSMS(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		var mobileno = $("#FORM_USERINFO_MOBILE__SMS_NO").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(mobileno, publicKey, function(encrypted) {
			var encryptedMobNo = encrypted;
				document.forms[0].FORM_USERINFO_MOBILE__SMS_NO.value = encryptedMobNo; 	
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}
function encryptNetsecureNRIRegSMS(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		var mobileno = $("#FORM_USERINFO_MOBNUM_SMS").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(mobileno, publicKey, function(encrypted) {
			var encryptedMobNo = encrypted;
				document.forms[0].FORM_USERINFO_MOBNUM_SMS.value = encryptedMobNo; 	
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function encryptNetsecureRegWEB(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		var mobileno = $("#FORM_USERINFO_MOBILE_WebPin_NO").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(mobileno, publicKey, function(encrypted) {
			var encryptedMobNo = encrypted;
				document.forms[0].FORM_USERINFO_MOBILE_WebPin_NO.value = encryptedMobNo; 	
			     
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function encryptNetsecureRegHW(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		var mobileno = $("#FORM_USERINFO_MOBILE_HW_NO").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(mobileno, publicKey, function(encrypted) {
			var encryptedMobNo = encrypted;
				document.forms[0].FORM_USERINFO_MOBILE_HW_NO.value = encryptedMobNo; 	
			     
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function encryptNetsecureRegMobi(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
	var mobileno = $("#FORM_USERINFO_MOBILE_MOBITOKEN_NO").val();

loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(mobileno, publicKey, function(encrypted) {
			var encryptedMobNo = encrypted;
				document.forms[0].FORM_USERINFO_MOBILE_MOBITOKEN_NO.value = encryptedMobNo; 	
			    
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function encryptNetsecureRegNRIWebMobi(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		var email = $("#FORM_USERINFO_EMAIL_WebPin_NO").val();
		var passno = $("#FORM_USERINFO_PNO_WebPin_NO").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(email, publicKey, function(encryptedemail) {
			var encryptedEmail = encryptedemail;
			jQuery.jCryption.encrypt(passno, publicKey, function(encryptedPassno) {
				var encryptedPassNo = encryptedPassno;
				document.forms[0].FORM_USERINFO_EMAIL_WebPin_NO.value = encryptedEmail; 
				document.forms[0].FORM_USERINFO_PNO_WebPin_NO.value = encryptedPassNo; 	
			     
			});
		});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function encryptPasswordExpiry(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		
		var oldLoginPassword = $("#oldLoginPassword").val();
		var newPassword = $("#newPassword").val();
		var newCnfrmPassword = $("#confirmloginPassword").val();
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});
		jQuery.jCryption.encrypt(oldLoginPassword, publicKey, function(encryptedOldLoginPwd) {
			var encryptedOldLoginPassword = encryptedOldLoginPwd;
			jQuery.jCryption.encrypt(newPassword, publicKey, function(encryptedNewPasswd) {
				var encryptedNewPassword = encryptedNewPasswd;
				jQuery.jCryption.encrypt(newCnfrmPassword, publicKey, function(encryptedNewCnfrmPasswd) {
					var encryptedNewCnfrmPassword = encryptedNewCnfrmPasswd;
					document.forms[0].FORM_LOGIN_PSWD.value = encryptedOldLoginPassword; 
					document.forms[0].FORM_NEW_LOGIN_PSWD.value = encryptedNewPassword; 	
					document.forms[0].FORM_NEW_LOGIN_PSWD_CONFIRM.value = encryptedNewCnfrmPassword; 
				});
			});
		});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}


function encryptForcePasswordChange(){	
	var bUrl = $("#JS_ENCRYPT_KEY_Public").val();
	var callbackFunc = function(publicKey){   
		
		var oldLoginPassword = $("#oldLoginPassword").val();
		var newPassword = $("#newPassword").val();
		var newCnfrmPassword = $("#confirmloginPassword").val();
		
		var oldTransPassword = $("#OldtransPassword").val();
		var newTransPassword = $("#newTransPassword").val();
		var newCnfrmTransPassword = $("#confirmnewTransPassword").val();
		
loadScript("https://retail.axisbank.co.in/jquery.jcryption.js", function() {
		});

		jQuery.jCryption.encrypt(oldLoginPassword, publicKey, function(encryptedOldLoginPwd) {
			var encryptedOldLoginPassword = encryptedOldLoginPwd;
			jQuery.jCryption.encrypt(newPassword, publicKey, function(encryptedNewPasswd) {
				var encryptedNewPassword = encryptedNewPasswd;
				jQuery.jCryption.encrypt(newCnfrmPassword, publicKey, function(encryptedNewCnfrmPasswd) {
					var encryptedNewCnfrmPassword = encryptedNewCnfrmPasswd;
									document.forms[0].FORM_LOGIN_PSWD.value = encryptedOldLoginPassword; 
									document.forms[0].FORM_NEW_LOGIN_PSWD.value = encryptedNewPassword; 	
									document.forms[0].FORM_NEW_LOGIN_PSWD_CONFIRM.value = encryptedNewCnfrmPassword; 
									$("#forcePwdChange").submit(); 
					});
				});
			});
	};
	getPublicKeyFromServer(callbackFunc,bUrl);
}

function getPublicKeyFromServer(callback,bUrl){
	var jCryptionKeyPair = function (encryptionExponent, modulus, maxdigits) {
	    setMaxDigits(parseInt(maxdigits, 10));
	    this.e = biFromHex(encryptionExponent);
	    this.m = biFromHex(modulus);
	    this.chunkSize = 2 * biHighIndex(this.m);
	    this.radix = 16;
	    this.barrett = new BarrettMu(this.m);
	};
	var encryptionExponent = bUrl.split(",")[0];
	var modulus = bUrl.split(",")[1];
	var maxDigits = bUrl.split(",")[2];
	var keys= new jCryptionKeyPair(encryptionExponent, modulus, maxDigits);
	callback(keys);
}
