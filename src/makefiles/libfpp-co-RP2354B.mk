ifeq '$(ARCH)' 'Raspberry Pi'

OBJECTS_fpp_co_RP2354B_so += channeloutput/RP2354BOutput.o
LIBS_fpp_co_RP2354B_so=-L. -lfpp -ljsoncpp

TARGETS += libfpp-co-RP2354B.$(SHLIB_EXT)
OBJECTS_ALL+=$(OBJECTS_fpp_co_RP2354B_so)

libfpp-co-RP2354B.$(SHLIB_EXT): $(OBJECTS_fpp_co_RP2354B_so) libfpp.$(SHLIB_EXT)
	$(CCACHE) $(CC) -shared $(CFLAGS_$@) $(OBJECTS_fpp_co_RP2354B_so) $(LIBS_fpp_co_RP2354B_so) $(LDFLAGS) $(LDFLAGS_fpp_co_RP2354B_so) -o $@

endif
