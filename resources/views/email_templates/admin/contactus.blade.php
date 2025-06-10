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
                      <p style="color:#028B8B;text-align:left;font-weight:bold;">Dear Admin</p>
                      <p style="color:#666666;text-align:left;">New contact us inquiry has been generated from the customer.</p>
						          <p style="color:#666666;text-align:left;">Following are the details</p>
                      @if(isset($allrequestdata['name']))
                      <p style="color:#666666;text-align:left;"><b>Name: </b>{{$allrequestdata['name']}}</p>
                      @endif
                      @if(isset($allrequestdata['email']))
                      <p style="color:#666666;text-align:left;"><b>Email: </b>{{$allrequestdata['email']}}</p>
                      @endif
                      @if(isset($allrequestdata['phone']))
                      <p style="color:#666666;text-align:left;"><b>Phone: </b>{{$allrequestdata['phone']}}</p>
                      @endif
                      @if(isset($allrequestdata['message']))
                      <p style="color:#666666;text-align:left;"><b>Message: </b>{{$allrequestdata['message']}}</p>
                      @endif
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