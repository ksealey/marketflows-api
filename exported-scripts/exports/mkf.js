const Cookies = require('js-cookie');
const axios   = require('axios');

$mkf = {
    _activityEndpoint: 'http://insights.marketflows.io',
    _appEndpoint: 'http://localhost/v1',
    campaigns:[
        {
            uuid: ,
            active: ,
        }
    ],
    startSession: function(campaign){
        var body = {};
        if( campaign && campaign.active ){
            
        }
        axios.post(this._appEndpoint + '/web-sessions', body)
             .then(function(r){

             });
    },

    //  Cookies
    getCookie: function(name){
        //  Get a cookie
        var cookie = Cookies.get(name);
        if( cookie === undefined )
            return undefined;
        if( parseFloat(cookie) || parseFloat(cookie) === 0 )
            return parseFloat(cookie);
        try{
            var obj = JSON.parse(cookie);

            return obj;
        }catch(e){}

        return cookie;
    },
    setCookie: function(name, value, config){
        //  Set a cookie
        Cookies.set(name, value, config);
    },

    // 
    //  Application logic
    //
    //  ...

    //
    //  Utility functions
    // 

}

module.exports = $mkf;