<!DOCTYPE HTML>
<html lang="en">
    <head>
        <title>@yield('title')</title>
        <meta charset="UTF-8">
        <style>
            @import  url("https://fonts.googleapis.com/css?family=Roboto:300,400,700&display=swap");
            *{
                font-family: 'Roboto';
                box-sizing: border-box;
            }
            .word-breaks{
                word-break: break-all;
            }
            .bold{
                font-weight: bold;
            }
            html,
            body{
                position: relative;
                width: 100%;
                height: 100%;
            }
            .container{
                box-sizing: border-box;
                display: block;
                width: 100%;
                height:100%;
                position: relative;
                background: #E9EBF2;
                padding: 20px;
            }
            .page{
                display: block;
                padding: 20px;
                margin: 20px auto;
                width: 500px;
                background: white;
                border-radius: 5px;
                border: solid #ddd 1px;
            }
            .logo{
                display: block;
                height: 40px;
                border-bottom: solid #E9EBF2 2px;
                margin-bottom: 10px;
                padding-bottom: 10px;
            }
            .logo img{
                max-width: 100%;
                max-height: 100%;
            }
            .header-block{
                text-align: center;
                color: #7878E8;
                padding: 5px;
                font-size: 22px;
            }
            .content{
                padding: 20px 30px 20px 30px;
                font-size: 14px;
            }
            .button{
                font-size: 14px;
                color: white;
                background-color: #7878E8;
                display: block;
                margin: 40px auto;
                padding: 10px 10px;
                text-align: center;
                max-width: 50%;
                border-radius: 2px;
            }
            a.button{
                color: white;
                text-decoration: none;
            }
            .footer{
                margin-top: 40px;
                border-top:solid #E9EBF2 2px;
                padding: 10px 0;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="page">
                <div class="logo">
                    <img alt="MarketFlows logo" src="https://marketflows.s3.amazonaws.com/assets/images/logo.png"/>
                </div>
                @yield('content')
                <div class="footer">
                    <b style="font-size: 13px">MarketFlows, LLC</b><br/>PO Box 310384<br/>Tampa, FL 33680
                </div>
            </div>
        </div>
    </body>
</html>