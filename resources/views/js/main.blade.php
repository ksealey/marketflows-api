<script>
    mkf = window['mkf'] || function(){};
    //  Start a new session if not started
    if( ! window.sessionStorage.getItem('session') ){
        //  Store session and get a new number
        var session = {
            'id': (new Date()) + (Math.random()),
            'browser': '',
            'device': '', 
        }
    }else{
        //  Run swap immediately
    } 
    
    //  Swap numbers
    //  ...

    //  Attach to document to track clicks
    //  ... 

    //  Track page view 
    //  ... 

    //  Execute actions in queue
    //  ...
   
    mkf.getSession=function()
    {
        var session = window.sessionStorage.getItem('session');
        if( session ) return JSON.parse(session);
        var req = new XmlHttpRequest();
        req.onreadystatechanged = function(){

        }
        req.open({{route('session')}})
    }

/*
    mfk.event = function(type,data)
    {
        var req = new XmlHttpRequest();
        req.onreadystatechanged = function(){

        }
        req.open('http://marketflows.io' + path.trim('/') );
        var f = new FormData();
        for(var p in params){
            if( ! params[p].hasOwnProperty(p) )
                continue;
            f.append(p,params[p]);
        }
        req.send(f);
    }

   */ 

    

    //  Run commans in queue

    
</script>