var xmlHttp;

function checkConsent(consentValue, show_spid, checkAction)
{ 
	xmlHttp=GetXmlHttpObject()
	if (xmlHttp==null) {
 		alert ("Browser does not support HTTP Request")
 		
 		return
	}
	
	var url="consentAdmin.php"
	url=url+"?cv="+consentValue
	url=url+"&action="+checkAction
	url=url+"&sid="+Math.random()
	xmlHttp.onreadystatechange=function() { 
	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete")
	{ 
		setConsentText(xmlHttp.responseText, show_spid);
	} 
}

	xmlHttp.open("GET",url,true)
	xmlHttp.send(null)
}

// This function will be automaticly called when the Ajax call is done returning data
function stateChanged() { 
	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete")
	{ 
		//Alert("Status of consent:"  + xmlHttp.responseText );
	} 
}

// This function creates an XMLHttpRequest
function GetXmlHttpObject() {
	var xmlHttp=null;
	try
 	{
		// Firefox, Opera 8.0+, Safari
		xmlHttp=new XMLHttpRequest();
	}
	catch (e)
	{
		//Internet Explorer
		try
		{
			xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e)
		{
			xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
		}
	}
	
	return xmlHttp;
}

function toggleShowAttributes(show_spid) {
	var disp = document.getElementById('attributes_' + show_spid);
	//var showhide = document.getElementById('showhide_' + show_spid);
	var showing = document.getElementById('showing_' + show_spid);
	var hiding = document.getElementById('hiding_' + show_spid);
	
	disp.style.display = (disp.style.display == 'none' ? 'block' : 'none');
	//showhide.innerHTML = (disp.style.display == 'none' ? 'Show' : 'Hide')
	showing.style.display = (disp.style.display == 'none' ? 'inline' : 'none');
	hiding.style.display = (disp.style.display == 'none' ? 'none' : 'inline');
	//alert('hiding display'+hiding.display);
}
