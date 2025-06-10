<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
@php
$settings=\App\Helpers\AppHelper::get_site_settings();
@endphp
<html>
  <head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>Email</title>
    <link href="{{ asset('assets/admin/pdf/css/bootstrap.min.css') }}" rel="stylesheet" media="all">
    <style type="text/css">
      body {background: #ffffff; font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif; font-size:16px; line-height:150%; color:#444; margin:0; padding:0;}
        p,li {margin-bottom:14px; margin-top:0;font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif;font-size:16px;color:#999999; line-height:150%;}
        td, div {font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif;font-size:13px; }
        a {color:#fff !important;text-decoration: none;}
        h1 {margin-top:15px;padding:0; color:#D70000; font-size:30px; line-height:34px;font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif;font-weight: normal;}
        h2 {margin-top:0; color:#D70000; font-size:18px;font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif;font-weight:normal;}
        h3 {margin:0;font-family:"Lucida Grande", "Helvetica Neue", Helvetica, Arial, sans-serif;}
        .bgItem{background: #D70000;}   
        ul {list-style-image: url(images/bullet.png);}
		.customemailbutton{
			background-color: #D70000;
			padding: 10px;
			border: 0;
			color: #fff;
		}

  @media only screen and (max-width:480px)
		
{
		
table[class="MainContainer"], td[class="cell"] 
	{
		width: 100% !important;
		height:auto !important; 
	}
td[class="specbundle"] 
	{
		width: 100% !important;
		float:left !important;
		font-size:13px !important;
		line-height:17px !important;
		display:block !important;
		padding-bottom:15px !important;
	}	
td[class="specbundle2"] 
	{
		width:90% !important;
		float:left !important;
		font-size:14px !important;
		line-height:18px !important;
		display:block !important;
		padding-left:5% !important;
		padding-right:5% !important;
	}
	td[class="specbundle3"] 
	{
		width:90% !important;
		float:left !important;
		font-size:14px !important;
		line-height:18px !important;
		display:block !important;
		padding-left:5% !important;
		padding-right:5% !important;
		padding-bottom:20px !important;
	}
		
td[class="spechide"] 
	{
		display:none !important;
	}
	    img[class="banner"] 
	{
	          width: 100% !important;
	          height: auto !important;
	}
		td[class="left_pad"] 
	{
			padding-left:15px !important;
			padding-right:15px !important;
	}
		 
}
	
@media only screen and (max-width:540px) 

{
		
table[class="MainContainer"], td[class="cell"] 
	{
		width: 100% !important;
		height:auto !important; 
	}
td[class="specbundle"] 
	{
		width: 100% !important;
		float:left !important;
		font-size:13px !important;
		line-height:17px !important;
		display:block !important;
		padding-bottom:15px !important;
	}	
td[class="specbundle2"] 
	{
		width:90% !important;
		float:left !important;
		font-size:14px !important;
		line-height:18px !important;
		display:block !important;
		padding-left:5% !important;
		padding-right:5% !important;
	}
	td[class="specbundle3"] 
	{
		width:90% !important;
		float:left !important;
		font-size:14px !important;
		line-height:18px !important;
		display:block !important;
		padding-left:5% !important;
		padding-right:5% !important;
		padding-bottom:20px !important;
	}
		
td[class="spechide"] 
	{
		display:none !important;
	}
	    img[class="banner"] 
	{
	          width: 100% !important;
	          height: auto !important;
	}
		td[class="left_pad"] 
	{
			padding-left:15px !important;
			padding-right:15px !important;
	}
		
	.font{
		font-size:26px !important;
		line-height:29px !important;
		
		}
}
		 .socialtd .contentEditableContainer{
			 display: inline-block;
			 margin-right: 30px;
			margin-bottom: 15px;
		}
		.DataTable{
			width: 100%;
			border: 1px solid #ddd;
			border-radius: 5px;
			margin-bottom: 20px;
		}
		.DataTable tr{}
		.DataTable tr td{
			padding: 6px 10px;
			border-bottom: 1px solid #ddd;
		}
		.DataTable tr:last-child td{
			border-bottom: 0;
		}
      </style>
    <script type="colorScheme" class="swatch active">
        {
          "name":"Default",
          "bgBody":"#ffffff",
          "link":"#013275",
          "color":"303841",
          "bgItem":"D70000",
          "title":"#D70000"
        }
      </script>
  </head>
    <body paddingwidth="0" paddingheight="0" bgcolor="#d1d3d4"  style="padding-top: 0; padding-bottom: 0; padding-top: 0; padding-bottom: 0; background-repeat: repeat; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-font-smoothing: antialiased;" offset="0" toppadding="0" leftpadding="0">
    <!-- main container -->
    	<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tbody>
    <tr>
      <td class='movableContentContainer' align="center">
      		<div class="movableContent" style="border: 0px; padding-top: 0px; position: relative;">
            	<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" class="MainContainer">
              <tbody>
                <tr>
                  <td align="center" colspan="9" height="20">
                  </td>
                </tr>
                <tr>
                	<td><table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tbody>
    <tr>
      <td class="specbundle3" style="text-align:center;width:100%;"><table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tbody>
    <tr>
      <td valign="top" height="60" width="100%">
                    <div class="contentEditableContainer contentImageEditable">
                      <div class="contentEditable" style="text-align: center;">
                        <img data-default="placeholder" data-max-width="250" data-max-height="100" src="{{ asset('assets/frontend/images/logo_icon.png') }}" width="50" height="" ></div>
                    </div>
                  </td>
      <td width="15">&nbsp;</td>
      <td width="330" valign="middle">
                    <div class="contentEditableContainer contentTextEditable">
                      <div class="contentEditable">
                        <h1 style="text-align:left;">
                          </h1>
                      </div>
                    </div>
                  </td>
    </tr>
  </tbody>
</table>
</td>
      <td width="20" class="specbundle3"></td>
      <td class="specbundle3" align="center"><table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tbody>
  </tbody>
</table>
</td>
    </tr>
  </tbody>
</table>
</td>
                </tr>
                <tr>
                <td align="center" colspan="9" height="20">
                </td>
              </tr>
              </tbody>
            </table>
            </div>
            <!-- END: Header: Logo; company name; website nav bar - Image + text -->
            <!-- Featured Image -->
    @yield('content')
  
            <div class="movableContent" style="border: 0px; padding-top: 0px; position: relative;">
            	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#013275;">
  <tbody>
    <tr>
      <td><table align="center" border="0" cellpadding="0" cellspacing="0" width="600" class="MainContainer">
              <tr>
                <td height="40">
                </td>
              </tr>
              <tr>
              <td><table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tbody>
    <tr>
		<td valign="top" class="specbundle3 socialtd" align="center"><p style="color:#fff;"><strong></strong>{{$settings->phone}}</p></td>
	  </tr>
    <tr>
		<td valign="top" class="specbundle3 socialtd" align="center"><p style="color:#fff !important;"><strong></strong>{{$settings->email}}</p></td>
	  </tr>
    <tr>
		<td valign="top" class="specbundle3 socialtd" align="center"><p style="color:#fff;"><strong></strong>{{$settings->address}}</p></td>
	  </tr>
  </tbody>
</table>
</td>
            </tr>
            <tr>
              <td height="10">
              </td>
            </tr>
            </table></td>
    </tr>
  </tbody>
</table>

            </div></td>
    </tr>
  </tbody>
</table>

  </body>
  </html>