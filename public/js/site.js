(function () {

    // TODO do not display strings in here or any js file, only display strings that have been sent by the server

    $(document).on('ready', function () {

        var viewportData = getViewport();
        var viewportWidth = viewportData[0];
        var viewportHeight = viewportData[1];

        $('.panel-container').css('max-height', viewportHeight - 50).css('height', viewportHeight - 50);

        var md = $('#messages');
        var commandInput = $('#command-input');

        var ticker;

        // site ready
        console.log('site ready');

        // input history
        commandInput.inputHistory({
            size: 5
        });

        $('#panel-container').on('click', '.panel-heading .close', function(e){
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

        // resize message div to be full height
        var vph = $(window).height() - 5;
        md.css({'height': vph + 'px', 'max-height': vph + 'px'});

        // websocket stuff
        conn = new WebSocket(wsprotocol + '://' + wshost + ':' + wsport);
        // even listener for connection open
        conn.onopen = function(e) {
            commandInput.detach();
            // fluff
            md.append('<span class="text-info">Establishing connection to NeoCortex network...</span><br />');
            // check username
            md.append('<span class="text-muted">login: </span>');
            commandInput.appendTo(md).focus();
        };
        // event listener for server message
        conn.onmessage = function(e) {
            var messageArray;
            var textClass = 'muted';
            var data = JSON.parse(e.data);
            var command = data.command;
            if (command !== 'getipaddy' && command !== 'showmessageprepend' && command !== 'updateprompt' && command !== 'ticker') commandInput.attr('type', 'text').detach();
            prompt = (data.prompt) ? data.prompt : 'INVALID PROMPT';
            switch (command) {
                default:
                    console.log('=== unknown command received ===');
                    break;
                case 'getipaddy':
                    var ipaddy = $('#ipaddy').val();
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
                    showprompt();
                    ticker = window.setInterval(function(){
                        jsonData = {
                            command: 'parseInput',
                            hash: hash,
                            content: 'ticker',
                            silent: true
                        };
                        conn.send(JSON.stringify(jsonData));
                    }, 1000);
                    break;
                case 'ticker':
                    var notiAmount = data.amount;
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
                    break;
                case 'entermailmode':
                    consoleMode = 'mail';
                    consoleOptionsMail.currentMailNumber = (data.mailNumber < 1) ? 0 : 1;
                    md.append(data.message);
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
                    if (!data.silent) showprompt();
                    break;
                case 'showmessage':
                    md.append(data.message);
                    showprompt();
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
                    var lastPrompt = $('.prompt').last();
                    $(data.message).insertBefore(lastPrompt);
                    return true;
            }
            if (command !== 'echocommand' && command !== 'updateprompt' && command !== 'ticker') {
                var lastOutput = $('#messages div.output-line:last');
                console.log(lastOutput);
                commandInput.appendTo(lastOutput).focus();
            }
        };
        conn.onclose = function() {
            md.append('<br /><span class="text-danger">Connection to NeoCortex network lost</span>');
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
