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
