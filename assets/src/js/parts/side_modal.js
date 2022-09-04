let sideModal = class {
    constructor()
    {
        let markup = `
            <div id="side_modal_cover"></div>

            <div id="side_modal" class="hidden" >
                <div id="side_modal_internal">
                    <div id="sm_header">
                        <span class="dismiss">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-circle-thin fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </span>
                        <div class="title">
                            <h5></h5>
                        </div>
                    </div>
                    <div class="slideDown loader" id="side_modal_loading_indicator">
                        Loading...
                    </div>
                    <div class="contentarea">
                        <div class="content"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('afterend', markup);

        this.triggers = document.querySelectorAll("[data-side-modal]");
        this.cover = document.getElementById('side_modal_cover');
        this.modal = document.getElementById('side_modal');
        this.header = document.getElementById('sm_header');
        this.closeButton = this.header.querySelector('.dismiss');
        this.titleEl = this.header.querySelector('h5');
        this.loader = this.modal.querySelector('.loader');
        this.contentArea = this.modal.querySelector('.content');
        this.isOpen = false;
        this.closeWithButtonOnly = false;
    }

    setUp() {
        document.addEventListener('click', this.shouldOpenSideModal.bind(this));

        this.triggers.forEach(trigger => {
            trigger.addEventListener("click", this.openSideModalAndLoadContent.bind(this));
        });

        this.cover.addEventListener("click", this.closeSideModalFromCover.bind(this));
        this.closeButton.addEventListener("click", this.closeSideModal.bind(this));

        document.addEventListener("keydown", this.closeWithEscKey.bind(this));
    }

    changeCloseWithButtonOnly(value)
    {
        if (typeof value == "boolean") {
            this.closeWithButtonOnly = value;
        }
    }

    closeWithEscKey(e)
    {
        if (e.keyCode === 27 && this.closeWithButtonOnly == false) {
            this.closeSideModal();
        }
    }

    closeSideModalFromCover()
    {
        if (this.closeWithButtonOnly == false) {
            this.closeSideModal();
        }
    }

    shouldOpenSideModal(e)
    {
        if (e.target && e.target.dataset.sideModal) {
            this.openSideModal();
        }
    }

    openSideModal(e)
    {
        this.isOpen = true;
        this.cover.classList.add('visible');
        this.modal.classList.remove('hidden');
        document.body.classList.add('ox-h');
    }

    openSideModalAndLoadContent(e)
    {
        e.preventDefault();
        this.isOpen = true;
        this.cover.classList.add('visible');
        this.modal.classList.remove('hidden');
        document.body.classList.add('ox-h');

        this.cleanAndSetLoading();
        this.setTitle(e.target.dataset.title);

        let url = e.target.href;
        let that = this;

        axios.get(url, {
        })
        .then(function (response) {
            that.setContent(response.data);
        })
        .catch(function (error) {
            toastr.error(json_strings.translations.cannot_load_content);

            that.setTitle('');
            that.setContent('');
            that.closeSideModal();
        });

    }

    closeSideModal()
    {
        if (this.isOpen) {
            this.isOpen = false;
            this.cover.classList.remove('visible');
            this.modal.classList.add('hidden');
            document.body.classList.remove('ox-h');

            this.clean();
        }
    }

    setTitle(title)
    {
        this.titleEl.innerHTML = title;
    }

    setContent(content)
    {
        this.contentArea.innerHTML = content;
        this.loader.classList.remove('visible');
    }

    clean()
    {
        this.contentArea.innerHTML = "";
    }

    cleanAndSetLoading()
    {
        this.clean();
        this.loader.classList.add('visible');
    }
};

window.smd = new sideModal();
window.smd.setUp();
