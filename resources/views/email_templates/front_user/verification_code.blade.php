@extends('email_templates.layouts.app')


@section('content')
<div class="movableContent" style="border: 0px; padding-top: 0px; position: relative;">
            	<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" class="MainContainer">
              <tr>
                <td height="20">
                </td>
              </tr>
              <tr>
                <td class="specbundle3">
                  <div class="contentEditableContainer contentTextEditable">
                    <div class="contentEditable">
                      <p style="color:#028B8B;text-align:left;font-weight:bold;">Dear {{$user->name}}</p>
                      <p style="color:#666666;text-align:left;">We received a request to reset your password.<br>Please use the verification code below to complete the process:</p>
						          <p style="color:#666666;text-align:left;">Your Verification Code: <b>{{$verfication_code}}</b></p>
						          <p style="color:#666666;text-align:left;">Thank You</p>
                    </div>
                  </div>
                </td>
              </tr>
              <tr>
                <td height="20">
                </td>
              </tr>
            </table>
            </div> <!-- END: Contact Us: Address -->
@endsection