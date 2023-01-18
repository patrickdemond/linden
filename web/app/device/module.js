cenozoApp.defineModule({
  name: "device",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "qnaire",
          column: "qnaire.id",
        },
      },
      name: {
        singular: "device",
        plural: "devices",
        possessive: "device's",
      },
      columnList: {
        name: {
          title: "Name",
          column: "device.name",
        },
        url: {
          title: "URL",
          column: "device.url",
        },
      },
      defaultOrder: {
        column: "device.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
      },
      url: {
        title: "URL",
        type: "string",
      },
    });

    module.addExtraOperation("view", {
      title: "Check Status",
      operation: async function ($state, model) {
        try {
          this.working = true;
          await model.viewModel.getDeviceStatus();
        } finally {
          this.working = false;
        }
      },
      isDisabled: function ($state, model) {
        return this.working;
      },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnDeviceViewFactory", [
      "CnBaseViewFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      function (CnBaseViewFactory, CnHttpFactory, CnModalMessageFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);

          this.getDeviceStatus = async function () {
            var modal = CnModalMessageFactory.instance({
              title: "Device Status",
              message:
                "Please wait while communicating with the device.",
              block: true,
            });

            modal.show();
            var response = await CnHttpFactory.instance({
              path: "device/" + this.record.id + "?action=status",
            }).get();
            modal.close();

            const status = angular.fromJson(response.data);
            await CnModalMessageFactory.instance({
              title: "Device Status " + ( null == status ? "(Offline)" : "(Online)" ),
              message: null == status ? "ERROR: There was no response from the device." : angular.toJson(status),
              error: null == status,
            }).show();
          };
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);
  },
});
