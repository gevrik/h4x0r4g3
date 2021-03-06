(function () {

    // TODO do not display strings in here or any js file, only display strings that have been sent by the server

    $(document).on('ready', function () {

        initSound();

        var viewportData = getViewport();
        viewportWidth = viewportData[0];
        viewportHeight = viewportData[1];

        $('.panel-container')
            .css('max-height', viewportHeight*0.425)
            .css('height', viewportHeight*0.425)
            .css('max-width', viewportWidth*0.33)
            .css('width', viewportWidth*0.33)
            .css('top', viewportHeight*0.5)
            .css('left', viewportWidth*0.65);

        $('.notifications-container')
            .css('max-height', viewportHeight*0.425)
            .css('height', viewportHeight*0.425)
            .css('max-width', viewportWidth*0.33)
            .css('width', viewportWidth*0.33)
            .css('top', viewportHeight*0.5)
            .css('left', viewportWidth*0.65);

        $('.map-container')
            .css('max-height', viewportHeight*0.425)
            .css('height', viewportHeight*0.425)
            .css('max-width', viewportWidth*0.33)
            .css('width', viewportWidth*0.33)
            .css('top', viewportHeight*0.005)
            .css('left', viewportWidth*0.65);

        $('.manpage-container')
            .css('max-height', viewportHeight*0.925)
            .css('height', viewportHeight*0.925)
            .css('max-width', viewportWidth*0.38)
            .css('width', viewportWidth*0.38)
            .css('top', viewportHeight*0.005)
            .css('left', viewportWidth*0.6);

        var md = $('#messages');
        var commandInput = $('#command-input');
        var passwordInput = $('#password-input');

        var ticker;

        // site ready
        console.log('well, hello there!');

        // input history
        commandInput.inputHistory({
            size: 20
        });

        function cmCallback (key, options) {
            var command = {
                command: 'parseInput',
                hash: hash,
                content: key
            };
            conn.send(JSON.stringify(command));
        }

        function cmCallbackNoSend(key, options) {
            commandInput.val(key + ' ').focus();
        }

        // context menu
        $.contextMenu({
            selector: '.img-tesseract',
            zIndex: 100,
            trigger: 'left',
            callback: function (key, options) {
                cmCallback(key,options);
            },
            items: {
                "score": {"name": "score", "icon": "fa-star"},
                "system": {"name": "system", "icon": "fa-info-circle"},
                "clear": {"name": "clear", "icon": "fa-eraser"},
                "commands": {"name": "commands", "icon": "fa-terminal"},
                "jobs": {"name": "jobs", "icon": "fa-cogs"},
                "missiondetails": {"name": "missioninfo", "icon": "fa-cog"},
                "ps": {"name": "ps", "icon": "fa-list-ol"},
                "sepa": "---------",
                "milkrun": {"name": "milkrun", "icon": "fa-comment"},
                "mission": {"name": "mission", "icon": "fa-commenting"},
                "sepb": "---------",
                "sneak": {"name": "sneak", "icon": "fa-user-secret"},
                "vis": {"name": "visible", "icon": "fa-user-o"},
                "sep1": "---------",
                "quit": {"name": "Quit", "icon": "fa-close", callback: function (key,options) {commandInput.focus()}},
                "sep2": "---------",
                "fold999": {
                    "name": "node-actions",
                    "items": {
                        "editnode": {"name": "editnode"},
                        "harvest": {"name": "harvest", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "scan": {"name": "scan", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "fold9992": {
                            "name": "bank",
                            "items": {
                                "deposit": {"name": "deposit", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "withdraw": {"name": "withdraw", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }}
                            }
                        }
                    }
                },
                "fold888": {
                    "name": "coding",
                    "items": {
                        "code": {"name": "code"},
                        "recipes": {"name": "recipes"},
                        "res": {"name": "resources"}
                    }
                },
                "fold1": {
                    "name": "movement",
                    "items": {
                        "connect": {"name": "connect", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "home": {"name": "home"}
                    }
                },
                "fold1a": {
                    "name": "entities",
                    "items": {
                        "consider": {"name": "consider", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "entityname": {"name": "entityname", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "eset": {"name": "eset", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }}
                    }
                },
                "fold1b": {
                    "name": "files",
                    "items": {
                        "decompile": {"name": "decompile", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "download": {"name": "download", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "equipment": {"name": "equipment"},
                        "execute": {"name": "execute", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "filecats": {"name": "filecategories"},
                        "filemods": {"name": "filemods"},
                        "filetypes": {"name": "filetypes"},
                        "initarmor": {"name": "initarmor", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "inv": {"name": "inventory"},
                        "kill": {"name": "kill", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "mod": {"name": "modfile", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "mods": {"name": "mods"},
                        "rm": {"name": "remove", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "unload": {"name": "unload", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "update": {"name": "update", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }}
                    }
                },
                "fold1c": {
                    "name": "building",
                    "items": {
                        "addnode": {"name": "addnode"},
                        "fold2": {
                            "name": "connections",
                            "items": {
                                "addconnection": {"name": "addconnection", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "removeconnection": {"name": "removeconnection", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "secureconnection": {"name": "secureconnection", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "unsecure": {"name": "unsecure", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }}
                            }
                        },
                        "fold2a": {
                            "name": "nodes",
                            "items": {
                                "editnode": {"name": "editnode"},
                                "nodename": {"name": "nodename", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "nodes": {"name": "nodes"},
                                "nodetype": {"name": "nodetype", callback: function (key, options) {
                                    cmCallbackNoSend(key,options);
                                }},
                                "removenode": {"name": "removenode"},
                                "upgradenode": {"name": "upgradenode"}
                            }
                        }
                    }
                },
                "fold1c1": {
                    "name": "chat",
                    "items": {
                        "fc": {"name": "factionchat", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "gc": {"name": "globalchat", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "newbie": {"name": "newbie", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "say": {"name": "say", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }}
                    }
                },
                "fold1d": {
                    "name": "factions",
                    "items": {
                        "factions": {"name": "factions"},
                        "factionratings": {"name": "factionratings"}
                    }
                },
                "fold1e": {
                    "name": "auctions",
                    "items": {
                        "auctionfile": {"name": "auctionfile", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "auctionbid": {"name": "auctionbid", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "auctionbids": {"name": "auctionbids"},
                        "auctionbuyout": {"name": "auctionbuyout", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "auctioncancel": {"name": "auctioncancel", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "auctionclaim": {"name": "auctionclaim", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "auctions": {"name": "auctions"}
                    }
                },
                "fold1f": {
                    "name": "feedback",
                    "items": {
                        "bug": {"name": "bug"},
                        "idea": {"name": "idea"},
                        "typo": {"name": "typo"}
                    }
                },
                "fold1w": {
                    "name": "wilderspace",
                    "items": {
                        "explore": {"name": "explore"}
                    }
                },
                "fold1x": {
                    "name": "milkruns",
                    "items": {
                        "defaultmra": {"name": "defaultmra", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "repairmra": {"name": "repairmra"},
                        "showmra": {"name": "showmra"},
                        "upgrademra": {"name": "upgrademra", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }}
                    }
                },
                "fold1y": {
                    "name": "account",
                    "items": {
                        "changepassword": {"name": "changepassword", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "invitations": {"name": "invitations"},
                        "setemail": {"name": "setemail"},
                        "setlocale": {"name": "setlocale"},
                        "options": {"name": "changeoption", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }},
                        "showbalance": {"name": "showbalance"},
                        "skillpoints": {"name": "skillpoints"},
                        "skills": {"name": "skills"}
                    }
                },
                "fold1z": {
                    "name": "interface",
                    "items": {
                        "bgopacity": {"name": "bgopacity", callback: function (key, options) {
                            cmCallbackNoSend(key,options);
                        }}
                    }
                }
            }
        });

        $.contextMenu({
            selector: '.contextmenu-connection',
            animation: {duration: 250, show: 'fadeIn', hide: 'fadeOut'},
            callback: function(key, options) {
                var m = "clicked: " + key;
            },
            items: {
                "secureconnection": {name: "secure", icon: "edit"},
                "sep1": "---------",
                "removeconnection": {name: "remove", icon: "delete"},
                "sep2": "---------",
                "quit": {name: "Quit", icon: function(){
                    return 'context-menu-icon context-menu-icon-quit';
                }}
            }
        });

        $('#messages').on('click', '.contextmenu-connection', function(e){
            var connectionId = $(this).data('id');
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'cd ' + connectionId
            };
            conn.send(JSON.stringify(command));
        });

        $('#messages').on('click', '.contextmenu-file', function(e){
            var fileId = $(this).data('id');
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'stat ' + fileId
            };
            conn.send(JSON.stringify(command));
        });

        $('#messages').on('click', '.contextmenu-entity', function(e){
            var entityId = $(this).data('id');
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'con ' + entityId
            };
            conn.send(JSON.stringify(command));
        });

        $('#panel-container').on('click', '.panel-heading .close', function(e){
            if (editor1) {
                editor1.destroy();
                editor1 = null;
            }
            $('#panel-container').html('').hide();
            commandInput.focus();
        });

        $('#notifications-container').on('click', '.panel-heading .close', function(e){
            $('#notifications-container').html('').hide();
            commandInput.focus();
        });

        $('#group-container').on('click', '.panel-heading .close', function(e){
            $('#group-container').html('').hide();
            commandInput.focus();
        });

        $('#coding-detail-container').on('click', '.panel-heading .close', function(e){
            $('#coding-detail-container').html('').hide();
            commandInput.focus();
        });

        $('#map-container').on('click', '.panel-heading .close', function(e){
            $('#map-container').html('').hide();
            commandInput.focus();
        });

        $('#gamepanels').on('click', '#panel-container .close-btn', function(e){
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

        $('#gamepanels').on('click', '#notifications-container #btn-dismiss-all-notifications', function(e){
            var command = {
                command: 'parseInput',
                hash: hash,
                content: 'dismissallnotifications',
                silent: true
            };
            conn.send(JSON.stringify(command));
            $('#notifications-container').html('').hide();
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
                command !== 'completemilkrun' &&
                !silent &&
                command !== 'ticker'
            ) {
                commandInput.attr('type', 'text').detach();
            }
            if (data.moved) {
                $('.contextmenu-connection').removeClass('contextmenu-connection');
                $('.contextmenu-file').removeClass('contextmenu-file');
                $('.contextmenu-entity').removeClass('contextmenu-entity');
            }
            prompt = (data.prompt) ? data.prompt : prompt;
            if (data.exitconfirmmode) {
                consoleMode = 'default';
            }
            switch (command) {
                default:
                    console.log('=== unknown command received ==='); // TODO remove for production
                    break;
                case 'getipaddy':
                    var ipaddy = $('#ipaddy').val();
                    if (ipaddy === '127.0.0.1') ipaddy = '137.74.164.116';
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
                            mymap.setView(myGeoCoords, 15);
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
                case 'closepanel':
                    $('#panel-container').html('').hide();
                    commandInput.focus();
                    break;
                case 'flytocoords':
                    mymap.flyTo([data.content[0], data.content[1]], 15);
                    break;
                case 'setbgopacity':
                    $('.content').css('background-color', 'rgba(0,0,0,' + data.content + ')');
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
                case 'enterinvitationcode':
                    loginStage = 'enterinvitationcode';
                    md.append('<div class="text-muted output-line">Please enter your invitation code:</div>');
                    break;
                case 'createpassword':
                    loginStage = 'createpassword';
                    md.append('<div class="text-muted output-line">Please enter a password for the new user: </div>');
                    var lastOutput = $('#messages div.output-line:last');
                    passwordInput.detach().appendTo(lastOutput).show().focus().attr('type', 'password');
                    break;
                case 'createpasswordconfirm':
                    loginStage = 'createpasswordconfirm';
                    md.append('<div class="text-muted output-line">Please confirm the password for the new user: </div>');
                    var lastOutput = $('#messages div.output-line:last');
                    passwordInput.detach().appendTo(lastOutput).show().focus().attr('type', 'password');
                    break;
                case 'createuserdone':
                    loginStage = 'complete';
                    hash = data.hash;
                    md.append('<span class="text-muted">A new user account and system has been generated.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1</span><br />');
                    $('.notification-box').show();
                    $('.actiontime-box').show();
                    $('.img-tesseract').show();
                    if (data.playsound) playSoundById(4);
                    showprompt();
                    break;
                case 'promptforpassword':
                    loginStage = 'promptforpassword';
                    md.append('<div class="text-muted output-line">password: </div>');
                    var lastOutput = $('#messages div.output-line:last');
                    passwordInput.detach().appendTo(lastOutput).show().focus().attr('type', 'password');
                    break;
                case 'logincomplete':
                    var jsonData;
                    loginStage = 'complete';
                    hash = data.hash;
                    md.append('<span class="text-muted">Authentication complete.</span><br />');
                    md.append('<span class="text-info">Welcome to NeoCortex OS v0.1 (ANONYMOUS ADWARE)</span><br />');
                    $('.notification-box').show();
                    $('.actiontime-box').show();
                    $('.img-tesseract').show();
                    if (data.playsound) playSoundById(4);
                    if (data.bgopacity) $('.content').css('background-color', 'rgba(0,0,0,' + data.bgopacity + ')');
                    if (data.homecoords[0] !== undefined) {
                        mymap.flyTo([data.homecoords[0], data.homecoords[1]], 15);
                    }
                    if (data.geocoords[0] !== undefined) {
                        mymap.flyTo([data.geocoords[0], data.geocoords[1]], 15);
                    }
                    break;
                case 'ticker':
                    var notiAmount = data.amount;
                    var unreadMails = data.unreadMails;
                    if (unreadMails > 0) {
                        $('.mail-notifier').show();
                    }
                    else {
                        $('.mail-notifier').hide();
                    }
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
                case 'getrandomgeocoords':
                    getRandomInRange(data.content, 6);
                    break;
                case 'showmap':
                    $('#map-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    //$('.panel-body-map').resizable();
                    if (!data.silent) showprompt();
                    break;
                case 'showcodingdetailpanel':
                    $('#coding-detail-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    if (!data.silent) showprompt();
                    break;
                case 'showgrouppanel':
                    $('#group-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    if (!data.silent) showprompt();
                    break;
                case 'showstorypanel':
                    $('#story-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    if (!data.silent) showprompt();
                    break;
                case 'shownotifications':
                    $('#notifications-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    if(document.getElementById('notification-container') !== null)
                    {
                        document.getElementById('notification-container').scrollTop = document.getElementById('notification-container').scrollHeight;
                    }
                    if (!data.silent) showprompt();
                    break;
                case 'showpanel':
                    $('#panel-container').html('').append(data.content).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
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
                    if (data.music) createMusicInstance(data.music);
                    $('#milkrun-container').html('').append(data.content).draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    }).css('z-index', panelZIndex++);
                    var milkrunMapWidth = $('#milkrun-panel').innerWidth();
                    $('#milkrun-game-container')
                        .css('max-height', milkrunMapWidth)
                        .css('height', milkrunMapWidth)
                        .css('z-index', panelZIndex++);
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
                case 'completemilkrun':
                    stopMusicInstance();
                    if (data.playsound) playSoundById(data.playsound);
                    $('#milkrun-container').html('');
                    var lastPrompt = $('.output-line').last();
                    var messageData = data.content;
                    $(messageData).insertBefore(lastPrompt);
                    document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
                    commandInput.focus();
                    return true;
                case 'openmanpagemenu':
                    $('#manpage-container').html('').append(data.message).show().draggable({
                        cursor: 'pointer',
                        handle: '.panel-heading',
                        stack: '#gamepanels div',
                        containment: '#messages'
                    });
                    $('#manpage-container').css('z-index', panelZIndex++);
                    $('#btn-close-manpage-editor').on('click', function(){
                        if (editor1) {
                            editor1.destroy();
                            editor1 = null;
                        }
                        $('#manpage-container').html('').hide();
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
                    var updatedElement = data.message.element;
                    var updatedValue = data.message.value;
                    if (updatedElement === '.current-eeg') {
                        $(updatedElement).empty().text(updatedValue);
                    }
                    else {
                        $(updatedElement).html(updatedValue);
                    }
                    break;
                case 'showmessage':
                    //getRandomInRange(0, 6);
                    md.append(data.message);
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
                    if (data.disconnectx) {
                        conn.close();
                    }
                    else {
                        showprompt();
                    }
                    break;
                case 'showoutput':
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        md.append(messageData);
                    });
                    if (!data.silent) showprompt();
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
                    if (data.disconnectx) {
                        conn.close();
                    }
                    break;
                case 'updateprompt':
                    commandInput.val(data.content);
                    break;
                case 'showmessageprepend':
                    var lastPrompt = $('.output-line').last();
                    $(data.message).insertBefore(lastPrompt);
                    document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
                    if (data.cleardeadline) {
                        $('.deadline-progress').hide();
                        clearInterval(deadlineTimer);
                    }
                    return true;
                case 'showoutputprepend':
                    var lastPrompt = $('.output-line').last();
                    messageArray = data.message;
                    $.each(messageArray, function(i, messageData){
                        $(messageData).insertBefore(lastPrompt);
                    });
                    document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
                    if (data.cleardeadline) {
                        $('.deadline-progress').hide();
                        clearInterval(deadlineTimer);
                    }
                    if (data.disconnectx) {
                        conn.close();
                    }
                    return true;
            }
            $('[data-toggle="tooltip"]').tooltip();
            $('#manpage-content-container ul a').map(function() {
                $(this).unbind().on('click', function(e) {
                    e.preventDefault();
                    console.log('man ' + $(this).attr('href'));
                    command = {
                        command: 'parseInput',
                        hash: hash,
                        content: 'man ' + $(this).attr('href'),
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
                command !== 'promptforpassword' &&
                command !== 'createpassword' &&
                command !== 'createpasswordconfirm' &&
                !silent
            ) {
                var lastOutput = $('#messages div.output-line:last');
                commandInput.appendTo(lastOutput).focus();
            }
        };

        // on connection close
        conn.onclose = function() {
            md.append('<br /><span class="text-danger">Connection to NeoCortex network lost - check <a href="https://wiki.h4x0r4g3.com" target="_blank">the WIKI</a> for server status</span>');
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
                            case 'solvecaptcha':
                                jsonData = {
                                    command: 'solvecaptcha',
                                    hash: hash,
                                    content: message
                                };
                                break;
                            case 'enterinvitationcode':
                                jsonData = {
                                    command: 'enterinvitationcode',
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
            // menu on esc
            if(keycode === 18){
                $('.img-tesseract').click();
            }
        })
            .on('keydown', '#password-input', function(event){
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
                                case 'createpassword':
                                    jsonData = {
                                        command: 'createpassword',
                                        hash: hash,
                                        content: message
                                    };
                                    passwordInput.hide();
                                    break;
                                case 'createpasswordconfirm':
                                    jsonData = {
                                        command: 'createpasswordconfirm',
                                        hash: hash,
                                        content: message
                                    };
                                    passwordInput.hide();
                                    break;
                                case 'promptforpassword':
                                    jsonData = {
                                        command: 'promptforpassword',
                                        hash: hash,
                                        content: message
                                    };
                                    passwordInput.hide();
                                    break;
                            }
                            break;
                    }
                    conn.send(JSON.stringify(jsonData));
                }
            })
            .on('click', function(){
                commandInput.focus();
            });
    });
}).call();
