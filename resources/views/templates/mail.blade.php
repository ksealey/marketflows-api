<!DOCTYPE HTML>
<html>
    <head>
        <style>
            @import url("https://fonts.googleapis.com/css?family=Roboto:300,400,700&display=swap");
            *{
                font-family: 'Roboto';
                box-sizing: border-box;
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
                margin: 10px 0 40px 0;
                font-size: 15px;
            }
            .button{
                color: white;
                background-color: #5B38D9;
                display: block;
                margin: 40px auto;
                padding: 15px;
                text-align: center;
                width: 50%;
                border-radius: 5px;
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="page">
                <div class="logo">
                    <img src="https://marketflows.s3.amazonaws.com/assets/images/logo-full.png"/>
                </div>
                @yield('content')
                <div class="footer">
                    <b style="font-size: 13px">MarketFlows,</b><br/>PO Box 310384<br/>Tampa, FL 33680
                </div>
            </div>
        </div>
    </body>
</html>