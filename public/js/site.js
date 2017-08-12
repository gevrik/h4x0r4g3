(function () {

    // TODO do not display strings in here or any js file, only display strings that have been sent by the server

    $(document).on('ready', function () {

        initSound();

        var viewportData = getViewport();
        var viewportWidth = viewportData[0];
        var viewportHeight = viewportData[1];

        $('.panel-container').css('max-height', viewportHeight - 50).css('height', viewportHeight - 50);

        var md = $('#messages');
        var commandInput = $('#command-input');

        var ticker;

        // site ready
        console.log('well, hello there!');

        // input history
        commandInput.inputHistory({
            size: 5
        });

        $('#panel-container').on('click', '.panel-heading .close', function(e){
            console.log('.close in .panel-heading clicked');
            $('#panel-container').html('');
            commandInput.focus();
        });

        $('#panel-container').on('click', '.close-btn', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'dismissnotification',
                silent: true,
                entityId: $(this).data('notification-id')
            };
            conn.send(JSON.stringify(command));
            commandInput.focus();
        });

        $('#panel-container').on('click', '#btn-dismiss-all-notifications', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'dismissallnotifications',
                silent: true
            };
            conn.send(JSON.stringify(command));
            $('#panel-container').html('');
            commandInput.focus();
        });

        $('.notification-box').on('click', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'shownotifications',
                silent: true
            };
            conn.send(JSON.stringify(command));
        });

        mymap = L.map('mapid').setView([51.505, -0.09], 15);
        L.tileLayer('https://cartodb-basemaps-{s}.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Map tiles by Carto, under CC BY 3.0. Data by OpenStreetMap, under ODbL.',
            maxZoom: 15
        }).addTo(mymap);
        mymap.removeControl(mymap.zoomControl);

        // resize message div to be full height
        var vph = $(window).height();
        md.css({'height': vph + 'px', 'max-height': vph + 'px'});

        // websocket stuff
        conn = new WebSocket(wsprotocol + '://' + wshost + ':' + wsport);
        // even listener for connection open
        conn.onopen = function(e) {
            commandInput.detach();
            // fluff
            md.append('<span class="text-info">Establishing connection to NeoCortex network...</span><br />');
            // check username
            md.append('<span class="text-muted">username: </span>');
            commandInput.appendTo(md).focus();
        };
        // event listener for server message
        conn.onmessage = function(e) {
            //console.log(e.data);
            var messageArray;
            var textClass = 'muted';
            var data = JSON.parse(e.data);
            var command = data.command;
            var silent = (data.silent) ? data.silent : false;
            if (command !== 'getipaddy' &&
                command !== 'showmessageprepend' &&
                command !== 'showoutputprepend' &&
                command !== 'updateprompt' &&
                command !== 'updatedivhtml' &&
                command !== 'updateinterfaceelement' &&
                !silent &&
                command !== 'ticker'
            ) commandInput.attr('type', 'text').detach();
            prompt = (data.prompt) ? data.prompt : prompt;
            if (data.exitconfirmmode) {
                consoleMode = 'default';
            }
            switch (command) {
                default:
                    console.log('=== unknown command received ===');
                    break;
                case 'getipaddy':
                    var ipaddy = $('#ipaddy').val();
                    //if (ipaddy === '127.0.0.1') ipaddy = '0.0.0.0';
                    var url = "https://freegeoip.net/json/";

                    if (ipaddy !== undefined) {
                        url = url + ipaddy;
                    } else {
                        //lookup our own ip address
                    }

                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", url, true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            var geoipdata = JSON.parse(xhr.response);
                            myGeoCoords = [geoipdata.latitude, geoipdata.longitude];
                            mymap.flyTo(myGeoCoords, 15);
                            jsonData = {
                                command: 'setgeocoords',
                                hash: hash,
                                content: myGeoCoords,
                                silent: true
                            };
                            conn.send(JSON.stringify(jsonData));
                        }
                    };
                    xhr.send(null);
                    jsonData = {
                        command: 'setipaddy',
                        hash: hash,
                        content: ipaddy,
                        silent: true
                    };
                    conn.send(JSON.stringify(jsonData));
                    break;
                case 'cd':
                    md.append(data.message);
                    showprompt();
                    break;
                case 'echocommand':
                    var lastOutput = $('#messages div.output-line:last');
                    if (loginStage !== 'createpassword' && loginStage !== 'createpasswordconfirm' && loginStage !== 'promptforpassword') {
                        //lastOutput.append(data.content + '<br />');
                        lastOutput.append(data.content);
                    }
                    break;
                case 'confirmusercreate':
                    loginStage = 'confirmusercreate';
                    md.append('<div class="text-muted output-line">Unknown user, do you want to create an account for this username? (y/n) </div>');
                    break;
                case 'solvecaptcha':
                    loginStage = 'solvecaptcha';
                    md.append('<div class="text-muted output-line">Please solve this: <img src="../temp/captcha.png" alt="captcha" /></div>');
                    break;
                case 'createpassword':
                    loginStage = 'createpassword';
                    //commandInput.attr('type', 'password');
                    md.append('<div class="text-muted output-line">Please enter a password for the new user: </div>');
                    break;
                case 'createpasswordconfirm':
                    loginStage = 'createpasswordconfirm';
                    //commandInput.attr('type', 'password');
                    md.append('<div class="text-muted output-line">Please confirm the password for the new user: </div>');
                    break;
                case 'createuserdone':
                    loginStage = 'complete';
                    hash = data.hash;
                    md.append('<span class="text-muted">A new user account and system has been generated.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1</span><br />');
                    showprompt();
                    break;
                case 'promptforpassword':
                    loginStage = 'promptforpassword';
                    //commandInput.attr('type', 'password');
                    md.append('<div class="text-muted output-line">password: </div>');
                    break;
                case 'logincomplete':
                    var jsonData;
                    loginStage = 'complete';
                    resetConsoleOptionsMail();
                    hash = data.hash;
                    md.append('<span class="text-muted">Authentication complete.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1 (ANONYMOUS ADWARE)</span><br />');
                    $('.notification-box').show();
                    $('.actiontime-box').show();
                    playSoundById(4);
                    break;
                case 'ticker':
                    var notiAmount = data.amount;
                    var actionTimeRemining = data.actionTimeRemaining;
                    $('.actiontime-box').removeClass('btn-default').removeClass('btn-info').addClass((actionTimeRemining)?'btn-info':'btn-default').html('<span>' + actionTimeRemining + '</span>');
                    if (notiAmount !== currentNotiAmount) {
                        currentNotiAmount = notiAmount;
                        $('.notification-box').removeClass('btn-default').removeClass('btn-info').addClass((notiAmount>0)?'btn-info':'btn-default').html('<span>' + notiAmount + '</span>');
                        if(document.getElementById('notification-container') !== null && notiAmount >= 1)
                        {
                            command = {
                                command: 'parseInput',
                                hash: hash,
                                content: 'shownotifications',
                                silent: true
                            };
                            conn.send(JSON.stringify(command));
                        }
                    }
                    break;
                case 'ls':
                    var directoryArray = data.message;
                    $.each(directoryArray, function(i, file){
                        switch (file.type) {
                            default:
                                textClass = 'muted';
                                break;
                            case 1:
                                textClass = 'directory';
                                file.name = '/' + file.name;
                                break;
                            case 2:
                            case 3:
                                textClass = 'executable';
                                break;
                            case 4:
                                textClass = 'textfile';
                                break;
                        }
                        if (file.running) {
                            file.name = '*' + file.name;
                        }
                        md.append('<span class="text-' + textClass + '">' + file.name + '</span><br />');
                    });
                    showprompt();
                    break;
                case 'clear':
                    md.html('');
                    showprompt();
                    break;
                case 'entercodemode':
                    consoleMode = 'code';
                    md.append(data.message);
                    showprompt();
                    break;
                case 'enterconfirmmode':
                    consoleMode = 'confirm';
                    md.append(data.message);
                    showprompt();
                    break;
                case 'entermailmode':
                    consoleMode = 'mail';
                    consoleOptionsMail.currentMailNumber = (data.mailNumber < 1) ? 0 : 1;
                    md.append(data.message);
                    showprompt();
                    break;
                case 'exitcodemode':
                    consoleMode = 'default';
                    showprompt();
                    break;
                case 'exitmailmode':
                    consoleMode = 'default';
                    resetConsoleOptionsMail();
                    showprompt();
                    break;
                case 'stopmilkrun':
                    playSoundById(2);
                    $('#milkrun-container').html('');
                    break;
                case 'completemilkrun':
                    playSoundById(data.playsound);
                    $('#milkrun-container').html('');
                    md.append(data.content);
                    showprompt();
                    break;
                case 'showpanel':
                    $('#panel-container').html('').append(data.content);
                    $('.draggable').draggable({
                        handle: '.panel-heading'
                    });
                    if(document.getElementById('notification-container') !== null)
                    {
                        $('#notification-container').css('max-height', viewportHeight - 50).css('height', viewportHeight - 50);
                        document.getElementById('notification-container').scrollTop = document.getElementById('notification-container').scrollHeight;
                    }
                    $('.btn-hangman-letter').on('click', function(){
                        var hangmanLetter = $(this).data('letter');
                        command = {
                            command: 'parseInput',
                            hash: hash,
                            content: 'hangmanletterclick ' + hangmanLetter,
                            silent: true
                        };
                        conn.send(JSON.stringify(command));
                    });
                    $('#btn-hangman-solve').on('click', function(){
                        var wordguess = $('#hangman-solution').val();
                        command = {
                            command: 'parseInput',
                            hash: hash,
                            content: 'hangmansolution ' + wordguess,
                            silent: true
                        };
                        conn.send(JSON.stringify(command));
                    });
                    if (!data.silent) showprompt();
                    break;
                case 'startmilkrun':
                    $('#milkrun-container').html('').append(data.content);
                    $('.draggable').draggable({
                        handle: '.panel-heading'
                    });
                    var milkrunMapWidth = $('#milkrun-panel').innerWidth();
                    $('#milkrun-game-container').css('max-height', milkrunMapWidth).css('height', milkrunMapWidth);
                    $('.milkrun-tile').attr('width', milkrunMapWidth/(data.level+4));
                    $('#milkrun-eeg').html(data.eeg);
                    $('#milkrun-attack').html(data.attack);
                    $('#milkrun-armor').html(data.armor);
                    $('.milkrun-clickable').on('click', function(){
                        var clickedX = $(this).data('x');
                        var clickedY = $(this).data('y');
                        command = {
                            command: 'parseInput',
                            hash: hash,
                            content: 'milkrunclick ' + clickedX + ' ' + clickedY,
                            silent: true
                        };
                        conn.send(JSON.stringify(command));
                    });
                    if (!data.silent) showprompt();
                    break;
                case 'openmanpagemenu':
                    $('#manpage-container').html('').append(data.message);
                    $('.draggable').draggable({
                        handle: '.panel-heading'
                    });
                    $('#btn-close-manpage-editor').on('click', function(){
                        if (editor1) {
                            editor1.destroy();
                            editor1 = null;
                        }
                        $('#manpage-container').html('');
                        commandInput.focus();
                    });
                    if (!silent) {
                        showprompt();
                    }
                    else {
                        commandInput.focus();
                    }
                    break;
                case 'updatedivhtml':
                    var targetElement = $(data.element);
                    targetElement.html('').append(data.content);
                    if (data.playsound) playSoundById(data.playsound);
                    if (data.element === '#milkrun-game-container') {
                        var milkrunMapWidth = $('#milkrun-panel').innerWidth();
                        $('#milkrun-game-container').css('max-height', milkrunMapWidth).css('height', milkrunMapWidth);
                        $('.milkrun-tile').attr('width', milkrunMapWidth/(data.level+4));
                        $('.milkrun-clickable').on('click', function(){
                            var clickedX = $(this).data('x');
                            var clickedY = $(this).data('y');
                            command = {
                                command: 'parseInput',
                                hash: hash,
                                content: 'milkrunclick ' + clickedX + ' ' + clickedY,
                                silent: true
                            };
                            conn.send(JSON.stringify(command));
                        });
                    }
                    break;
                case 'updateinterfaceelement':
                    console.log(data);
                    $(data.message.element).html(data.message.value);
                    break;
                case 'showmessage':
                    md.append(data.message);
                    showprompt();
                    if (data.deadline) {
                        $('.deadline-progress').show();
                        $('.deadliner').attr('data-maxseconds', data.deadline).attr('data-seconds', 0).css('width', '100%');
                        var deadlineTimer = setInterval(function(){
                            var newSeconds = Number($('.deadliner').attr('data-seconds')) + 1;
                            $('.deadliner').attr('data-seconds', newSeconds);
                            var onePercent = 100 / $('.deadliner').attr('data-maxseconds');
                            var remainingSeconds = Number($('.deadliner').attr('data-maxseconds')) - Number($('.deadliner').attr('data-seconds'));
                            $('.deadliner').css('width', (remainingSeconds * onePercent) + '%');
                            if (remainingSeconds === 0) {
                                $('.deadline-progress').hide();
                                clearInterval(deadlineTimer);
                            }
                        }, 1000);
                    }
                    if (data.cleardeadline) {
                        $('.deadline-progress').hide();
                        clearInterval(deadlineTimer);
                    }
                    break;
                case 'showoutput':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    showprompt();
                    break;
                case 'updateprompt':
                    commandInput.val(data.message);
                    break;
                case 'showmessageprepend':
                    var lastPrompt = $('.output-line').last();
                    $(data.message).insertBefore(lastPrompt);
                    document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
                    return true;
                case 'showoutputprepend':
                    var lastPrompt = $('.output-line').last();
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        $(messageData).insertBefore(lastPrompt);
                    });
                    document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
                    return true;
            }
            $('[data-toggle="tooltip"]').tooltip();
            $('#manpage-content-container ul a').map(function() {
                $(this).unbind().on('click', function(e) {
                    e.preventDefault();
                    console.log($(this).attr('id'));
                    command = {
                        command: 'parseInput',
                        hash: hash,
                        content: 'man ' + $(this).attr('id'),
                        silent: true
                    };
                    conn.send(JSON.stringify(command));
                });
            });
            // reattach input
            if (
                command !== 'echocommand' &&
                command !== 'updateprompt' &&
                command !== 'ticker' &&
                command !== 'updatedivhtml' &&
                command !== 'updateinterfaceelement' &&
                !silent
            ) {
                var lastOutput = $('#messages div.output-line:last');
                commandInput.appendTo(lastOutput).focus();
            }
        };

        // on connection close
        conn.onclose = function() {
            md.append('<br /><span class="text-danger">Connection to NeoCortex network lost - check https://wiki.h4x0r4g3.com for server status</span>');
            $('#command-input').remove();
            clearInterval(ticker);
        };

        // sending input
        $('#main-content').on('keydown', '#command-input', function(event){
            var keycode = (event.keyCode ? event.keyCode : event.which);
            var jsonData;
            var message = '';
            // enter
            if(keycode === 13) {
                // enter key pressed in input
                message = $(this).val();
                $.trim(message);
                $(this).val('');
                switch (consoleMode) {
                    default:
                        switch (loginStage) {
                            default:
                                conn.close();
                                break;
                            case 'login':
                                jsonData = {
                                    command: 'login',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'confirmusercreate':
                                jsonData = {
                                    command: 'confirmusercreate',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createpassword':
                                jsonData = {
                                    command: 'createpassword',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'createpasswordconfirm':
                                jsonData = {
                                    command: 'createpasswordconfirm',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'solvecaptcha':
                                jsonData = {
                                    command: 'solvecaptcha',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'promptforpassword':
                                jsonData = {
                                    command: 'promptforpassword',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'complete':
                                jsonData = {
                                    command: 'parseInput',
                                    hash: hash,
                                    content: message
                                };
                                break;
                        }
                        break;
                    case 'mail':
                        jsonData = {
                            command: 'parseMailInput',
                            hash: hash,
                            content: message,
                            mailOptions: consoleOptionsMail
                        };
                        break;
                    case 'code':
                        jsonData = {
                            command: 'parseCodeInput',
                            hash: hash,
                            content: message
                        };
                        break;
                    case 'confirm':
                        jsonData = {
                            command: 'parseConfirmInput',
                            hash: hash,
                            content: message
                        };
                        break;
                }
                conn.send(JSON.stringify(jsonData));
            }
            // tab
            if(keycode === 9){
                event.preventDefault();
                message = $(this).val();
                jsonData = {
                    command: 'autocomplete',
                    hash: hash,
                    content: message,
                    silent: true
                };
                conn.send(JSON.stringify(jsonData));
            }
        })
            .on('click', function(){
                commandInput.focus();
            });
    });
}).call();
