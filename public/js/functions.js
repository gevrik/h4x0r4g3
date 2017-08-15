function showprompt() {
    if (consoleMode === 'default') {
        if (promptAddon !== '') prompt = prompt + ' ' + promptAddon;
        $('#messages').append('<div class="text-muted output-line"><span>' + prompt + '</span></div>');
    }
    else {
        $('#messages').append('<div class="text-muted output-line"><span>&gt;&nbsp;</span></div>');
    }
}

/**
 * Reset the mail mode console options.
 */
function resetConsoleOptionsMail() {
    consoleOptionsMail = {
        currentMailNumber: 0
    };
}

function getViewport() {

    var viewPortWidth;
    var viewPortHeight;

    // the more standards compliant browsers (mozilla/netscape/opera/IE7) use window.innerWidth and window.innerHeight
    if (typeof window.innerWidth != 'undefined') {
        viewPortWidth = window.innerWidth,
            viewPortHeight = window.innerHeight
    }

// IE6 in standards compliant mode (i.e. with a valid doctype as the first line in the document)
    else if (typeof document.documentElement != 'undefined'
        && typeof document.documentElement.clientWidth !=
        'undefined' && document.documentElement.clientWidth != 0) {
        viewPortWidth = document.documentElement.clientWidth,
            viewPortHeight = document.documentElement.clientHeight
    }

    // older versions of IE
    else {
        viewPortWidth = document.getElementsByTagName('body')[0].clientWidth,
            viewPortHeight = document.getElementsByTagName('body')[0].clientHeight
    }
    return [viewPortWidth, viewPortHeight];
}

function initSound() {
    if (!createjs.Sound.initializeDefaultPlugins()) {
        return;
    }
    var assetsPath = "../sounds/";
    var sounds = [
        {src: "gotcredits.ogg", id: 1},
        {src: "accessdenied.ogg", id: 2},
        {src: "buttonpress.ogg", id: 3},
        {src: "logincomplete.ogg", id: 4},
        {src: "meleeattack.ogg", id: 5},
        {src: "revealunknownnode.ogg", id: 6},
        {src: "keyfound.ogg", id: 7},
        {src: "payloadfound.ogg", id: 8},
        {src: "milkrunnextlevel.ogg", id: 9},
        {src: "gotsnippets.ogg", id: 10}
    ];
    createjs.Sound.alternateExtensions = ["mp3"];
    createjs.Sound.addEventListener("fileload", createjs.proxy(soundLoaded, this));
    createjs.Sound.registerSounds(sounds, assetsPath);
}

function soundLoaded(event) {
    // sound loaded
}

function stopSound() {
    if (preload !== null) {
        preload.close();
    }
    createjs.Sound.stop();
}

function playSoundByClick(target) {
    var instance = createjs.Sound.play(target.id);
    if (instance === null || instance.playState === createjs.Sound.PLAY_FAILED) {
        console.log('play-sound-by-click-error');
        return;
    }
    instance.addEventListener("complete", function (instance) {
        // when complete
    });
}

function playSoundById(soundId) {
    var instance = createjs.Sound.play(soundId);
    if (instance === null || instance.playState === createjs.Sound.PLAY_FAILED) {
        console.log('play-sound-by-id-error');
        return;
    }
    instance.addEventListener("complete", function (instance) {
        // when complete
    });
}

var getGeoCoordsForIp = function (ip) {
    var url = "https://freegeoip.net/json/";

    if (ip !== undefined) {
        url = url + ip;
    } else {
        //lookup our own ip address
    }

    var xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log('setting gecoords');
            var geoipdata = JSON.parse(xhr.response);
            myGeoCoords = [geoipdata.latitude, geoipdata.longitude];
            mymap.setView(myGeoCoords, 15);
        }
    };
    xhr.send(null);
};

var zoneBoundsData = [
    {'name':'global', 'latfrom': -80, 'latto': 80, 'lngfrom': -180, 'lngto': 180},
    {'name':'aztech', 'latfrom': -54, 'latto': 71, 'lngfrom': -179, 'lngto': -29},
    {'name':'euro', 'latfrom': -35, 'latto': 71, 'lngfrom': -30, 'lngto': 55},
    {'name':'asia', 'latfrom': -47, 'latto': 71, 'lngfrom': -56, 'lngto': 180}
];

var getRandomInRange = function (zoneid, fixed) {
    var lat, lng;
    lat = (Math.random() * (zoneBoundsData[zoneid].latto - zoneBoundsData[zoneid].latfrom) + zoneBoundsData[zoneid].latfrom).toFixed(fixed) * 1;
    lng = (Math.random() * (zoneBoundsData[zoneid].lngto - zoneBoundsData[zoneid].lngfrom) + zoneBoundsData[zoneid].lngfrom).toFixed(fixed) * 1;
    return sendGeocodeRequest(lat, lng);
};

var sendGeocodeRequest = function (lat, lng) {
    var result = false;
    var xhr = new XMLHttpRequest();
    var url = 'http://maps.google.com/maps/api/geocode/json?address=' + lat + ',' + lng + '&sensor=false';
    var attempts = 0;
    while (!result && attempts < 3) {
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                var geoipdata = JSON.parse(xhr.response);
                console.log(geoipdata);
                if (geoipdata.status === "OK") {
                    var possibleLocations = [];
                    $.each(geoipdata.results, function(i, resultData){
                        $.each(resultData.types, function(ix, typeData){
                            if (
                                typeData === 'street_address' ||
                                typeData === 'intersection' ||
                                typeData === 'premise' ||
                                typeData === 'subpremise' ||
                                typeData === 'point_of_interest' ||
                                typeData === 'state' ||
                                typeData === 'country' ||
                                typeData === 'administrative_area_level_1' ||
                                typeData === 'administrative_area_level_2' ||
                                typeData === 'administrative_area_level_3' ||
                                typeData === 'administrative_area_level_4' ||
                                typeData === 'administrative_area_level_5'
                            )
                            {
                                possibleLocations.push(resultData);
                            }
                        });
                    });
                    console.log(possibleLocations);
                    if (possibleLocations.length >= 1) {
                        //result = possibleLocations[Math.floor(Math.random() * possibleLocations.length)];
                        //console.log(result);
                        var command = {
                            command: 'processlocations',
                            hash: hash,
                            content: possibleLocations,
                            silent: true
                        };
                        conn.send(JSON.stringify(command));
                    }
                }
            }
        };
        xhr.send(null);
        attempts++;
    }
};
