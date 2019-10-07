var $mkf = require('../exports/mkf');

test('MKF can handle cookies', function(){
    var strKey  = 'myCookie';
    var strVal  = 'Hello world';
    var objKey  = 'profile';
    var objVal  = {name: 'James', age: 40, orders: [ {id: 1, total: 10.00}, {id: 2, total: 50.25} ]};
    var arrKey  = 'itemIds';
    var arrVal  = [7, 55, 31];
    var numKey  = 'activeDays';
    var numVal  = 99;
    var fltKey  = 'spend';
    var fltVal  =  905.20;

    $mkf.setCookie(strKey, strVal);
    $mkf.setCookie(objKey, objVal);
    $mkf.setCookie(arrKey, arrVal);
    $mkf.setCookie(numKey, numVal);
    $mkf.setCookie(fltKey, fltVal);

    var str = $mkf.getCookie(strKey);
    expect(str).toBe(strVal);
    expect(typeof str).toBe('string');

    var obj = $mkf.getCookie(objKey);
    expect(obj).toMatchObject(objVal);
    expect(typeof obj).toBe('object');

    var arr = $mkf.getCookie(arrKey);
    expect(arr).toMatchObject(arrVal);
    expect(typeof arr).toBe('object');

    var num = $mkf.getCookie(numKey);
    expect(num).toBe(numVal);
    expect(typeof num).toBe('number');

    var flt = $mkf.getCookie(fltKey);
    expect(flt).toBe(fltVal);
    expect(typeof flt).toBe('number');
});