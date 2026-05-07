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
                      <p style="color:#666666;text-align:left;">We wanted to inform you that a user has reported a video on the platform. Below are the details:</p>
                      @if(isset($report->reported_by_name))
                      <p style="color:#666666;text-align:left;"><b>Name: </b>{{$report->reported_by_name}}</p>
                      @endif
                      @if(isset($report->reported_by_email))
                      <p style="color:#666666;text-align:left;"><b>Email: </b>{{$report->reported_by_email}}</p>
                      @endif
                      <p style="color:#666666;text-align:left;"><b>Category: </b>{{$report->category_name}}</p>
                      <p style="color:#666666;text-align:left;"><b>Comments: </b>{{$report->comments}}</p>
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